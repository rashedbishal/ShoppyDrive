<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DatabaseBackupFormat;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * OPS-008: restores a DatabaseBackupFormat file. New rows are added, changed
 * rows are updated, identical rows are skipped, and nothing is ever deleted.
 *
 * rehearse() and apply() are the same run inside one transaction, and only
 * apply() may commit. So the report comes from the database itself accepting
 * or refusing each row (types, lengths, required values, unique keys), not
 * from a guess, and any failure, including a timeout, leaves nothing behind.
 *
 * Rows are written with the query builder, so no model events fire: restoring
 * an order sends no email and doesn't touch stock, points or coupons.
 *
 * @phpstan-type Report array{ok: bool, committed: bool, rows: int, tables: list<array{table: string, added: int, updated: int, skipped: int, conflicts: int}>, errors: list<string>, error_count: int, warnings: list<string>}
 */
class DatabaseBackupRestorer
{
    private const int ROWS_PER_BATCH = 500;

    /** The report stays readable, and the session small, on a very bad file. */
    private const int MAX_MESSAGES = 50;

    /** @var array<string, array{table: string, added: int, updated: int, skipped: int, conflicts: int}> */
    private array $tables = [];

    /** @var list<string> */
    private array $errors = [];

    private int $errorCount = 0;

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, list<string>> */
    private array $knownColumns = [];

    /**
     * Everything apply() would do, then undone.
     *
     * @return Report
     */
    public function rehearse(string $path): array
    {
        return $this->run($path, commit: false);
    }

    /**
     * Commits only if the whole file passes.
     *
     * @return Report
     */
    public function apply(string $path): array
    {
        $report = $this->run($path, commit: true);

        if ($report['committed']) {
            Cache::flush();
        }

        return $report;
    }

    /**
     * @return Report
     */
    private function run(string $path, bool $commit): array
    {
        $this->tables = [];
        $this->errors = [];
        $this->errorCount = 0;
        $this->warnings = [];
        $this->knownColumns = array_fill_keys(DatabaseBackupFormat::tables(), []);
        $committed = false;

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();

        try {
            $this->readFile($path);
            $this->checkForeignKeys();

            if ($commit && $this->errorCount === 0) {
                DB::commit();
                $committed = true;
            }
        } catch (Throwable $exception) {
            $this->addError('The restore stopped: '.$this->reason($exception));
        } finally {
            if (! $committed) {
                DB::rollBack();
            }

            Schema::enableForeignKeyConstraints();
        }

        $tables = array_values($this->tables);

        return [
            'ok' => $this->errorCount === 0,
            'committed' => $committed,
            'rows' => array_sum(array_map(fn (array $table): int => $table['added'] + $table['updated'] + $table['skipped'] + $table['conflicts'], $tables)),
            'tables' => $tables,
            'errors' => $this->errors,
            'error_count' => $this->errorCount,
            'warnings' => $this->warnings,
        ];
    }

    private function readFile(string $path): void
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            $this->addError('The uploaded file could not be read.');

            return;
        }

        try {
            $this->readLines($file);
        } finally {
            fclose($file);
        }
    }

    /**
     * @param  resource  $file
     */
    private function readLines($file): void
    {
        $lineNumber = 0;
        $sawHeader = false;
        $declaredRows = null;
        $rowsRead = 0;
        $batchTable = null;
        $batch = [];

        while (($line = fgets($file)) !== false) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");

            if ($lineNumber === 1) {
                if ($line !== DatabaseBackupFormat::HEADER) {
                    $this->addError('This is not a ShoppyDrive backup file.');

                    return;
                }

                $sawHeader = true;

                continue;
            }

            if ($declaredRows !== null && $line !== '') {
                $this->addError("Line {$lineNumber}: there is content after the end of the backup.");

                return;
            }

            if ($line === '' || in_array($line, DatabaseBackupFormat::SETUP_LINES, true)) {
                continue;
            }

            if (str_starts_with($line, '-- ')) {
                $declaredRows = $this->readComment($line) ?? $declaredRows;

                continue;
            }

            $row = DatabaseBackupFormat::parseRow($line);

            if ($row === null) {
                $this->addError("Line {$lineNumber}: this line is not a data row from a backup, so the file was rejected.");

                continue;
            }

            $rowsRead++;

            $row = DatabaseBackupFormat::withoutRetiredColumns($row);

            if (! $this->acceptsColumns($row, $lineNumber)) {
                continue;
            }

            if ($batchTable !== $row['table'] || count($batch) >= self::ROWS_PER_BATCH) {
                $this->writeBatch((string) $batchTable, $batch);
                $batch = [];
                $batchTable = $row['table'];
            }

            $batch[] = [...$row, 'line' => $lineNumber];
        }

        $this->writeBatch((string) $batchTable, $batch);

        if ($sawHeader && $declaredRows === null) {
            $this->addError('The file is incomplete: the end of the backup is missing, so the download was probably cut off.');
        } elseif ($sawHeader && $declaredRows !== $rowsRead) {
            $this->addError("The file is damaged: it should hold {$declaredRows} rows but has {$rowsRead}.");
        } elseif (! $sawHeader) {
            $this->addError('The file is empty.');
        }
    }

    /**
     * @return int|null The row count, when this is the closing line.
     */
    private function readComment(string $line): ?int
    {
        if (str_starts_with($line, DatabaseBackupFormat::VERSION_PREFIX)
            && substr($line, strlen(DatabaseBackupFormat::VERSION_PREFIX)) !== (string) DatabaseBackupFormat::VERSION) {
            $this->addError('This backup was made in a format this version of the site cannot read.');
        }

        if (str_starts_with($line, DatabaseBackupFormat::MIGRATION_PREFIX)
            && substr($line, strlen(DatabaseBackupFormat::MIGRATION_PREFIX)) !== (string) DatabaseBackupFormat::latestMigration()) {
            $this->warnings[] = 'This backup was made on a different version of the database. Any real mismatch is listed as an error.';
        }

        if (str_starts_with($line, DatabaseBackupFormat::FOOTER_PREFIX)) {
            return (int) substr($line, strlen(DatabaseBackupFormat::FOOTER_PREFIX));
        }

        return null;
    }

    /**
     * @param  array{table: string, columns: list<string>, values: list<string|null>}  $row
     */
    private function acceptsColumns(array $row, int $lineNumber): bool
    {
        $table = $row['table'];

        if (! array_key_exists($table, $this->knownColumns)) {
            $this->addError("Line {$lineNumber}: table \"{$table}\" doesn't exist here or can't be restored.");

            return false;
        }

        if ($this->knownColumns[$table] === []) {
            $this->knownColumns[$table] = DatabaseBackupFormat::columns($table);
            $this->tables[$table] = ['table' => $table, 'added' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];
        }

        $unknown = array_diff($row['columns'], $this->knownColumns[$table]);

        if ($unknown !== []) {
            $this->addError("Line {$lineNumber}: table \"{$table}\" has no column \"".reset($unknown).'".');

            return false;
        }

        return true;
    }

    /**
     * @param  list<array{table: string, columns: list<string>, values: list<string|null>, line: int}>  $batch
     */
    private function writeBatch(string $table, array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $primaryKey = $this->primaryKey($table);
        $rows = array_map(fn (array $row): array => ['line' => $row['line'], 'data' => array_combine($row['columns'], $row['values'])], $batch);
        $existing = count($primaryKey) === 1 ? $this->existingByKey($table, $primaryKey[0], $rows) : null;

        foreach ($rows as $row) {
            $key = array_intersect_key($row['data'], array_flip($primaryKey));
            $current = match (true) {
                $existing !== null => $existing[(string) reset($key)] ?? null,
                $primaryKey !== [] && count($key) === count($primaryKey) => DB::table($table)->where($key)->first(),
                default => DB::table($table)->where($this->nullSafe($row['data']))->first(),
            };

            $this->writeRow($table, $key, $row['data'], $current === null ? null : (array) $current, $row['line']);
        }
    }

    /**
     * A table with no primary key is matched on the whole row.
     *
     * @param  array<string, string|null>  $data
     * @return list<array{0: string, 1: string, 2: string|null}>
     */
    private function nullSafe(array $data): array
    {
        return array_map(fn (string $column, ?string $value): array => [$column, '=', $value], array_keys($data), $data);
    }

    /**
     * @return list<string>
     */
    private function primaryKey(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return [];
    }

    /**
     * @param  list<array{line: int, data: array<string, string|null>}>  $rows
     * @return array<string, object>
     */
    private function existingByKey(string $table, string $primaryKey, array $rows): array
    {
        $keys = array_values(array_filter(array_map(fn (array $row): ?string => $row['data'][$primaryKey] ?? null, $rows), fn (?string $key): bool => $key !== null));

        return DB::table($table)->whereIn($primaryKey, $keys)->get()->keyBy($primaryKey)->all();
    }

    /**
     * @param  array<string, string|null>  $key
     * @param  array<string, string|null>  $data
     * @param  array<string, mixed>|null  $current
     */
    private function writeRow(string $table, array $key, array $data, ?array $current, int $line): void
    {
        if ($current !== null && $this->isSame($data, $current)) {
            $this->tables[$table]['skipped']++;

            return;
        }

        if ($current !== null && in_array($table, DatabaseBackupFormat::APPEND_ONLY_TABLES, true)) {
            $this->addConflict($table, "Line {$line}: a permanent record in \"{$table}\" differs from the backup, so it was kept as it is.");

            return;
        }

        try {
            if ($current === null) {
                DB::table($table)->insert($data);
                $this->tables[$table]['added']++;
            } else {
                DB::table($table)->where($key)->update($data);
                $this->tables[$table]['updated']++;
            }
        } catch (UniqueConstraintViolationException) {
            $this->addConflict($table, "Line {$line}: a different row in \"{$table}\" already holds this unique value, so this row was skipped.");
        } catch (QueryException $exception) {
            $this->addError("Line {$line}, table \"{$table}\": ".$this->reason($exception));
        }
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<string, mixed>  $current
     */
    private function isSame(array $data, array $current): bool
    {
        foreach ($data as $column => $value) {
            $stored = $current[$column] ?? null;

            if (($value === null) !== ($stored === null) || (string) $value !== (string) $stored) {
                return false;
            }
        }

        return true;
    }

    /**
     * Foreign keys are off during the run so that tables can load in any
     * order, and rows can point at each other, so they're proved afterwards.
     */
    private function checkForeignKeys(): void
    {
        foreach (array_keys($this->tables) as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (count($foreignKey['columns']) !== 1) {
                    continue;
                }

                $column = $foreignKey['columns'][0];
                $parent = $foreignKey['foreign_table'];
                $parentColumn = $foreignKey['foreign_columns'][0];

                $orphans = DB::table($table)
                    ->leftJoin("{$parent} as parent_row", "{$table}.{$column}", '=', "parent_row.{$parentColumn}")
                    ->whereNotNull("{$table}.{$column}")
                    ->whereNull("parent_row.{$parentColumn}")
                    ->count();

                if ($orphans > 0) {
                    $this->addError("{$orphans} row(s) in \"{$table}\" point to a {$parent} record that doesn't exist ({$column}).");
                }
            }
        }
    }

    private function addConflict(string $table, string $message): void
    {
        $this->tables[$table]['conflicts']++;

        if (count($this->warnings) < self::MAX_MESSAGES) {
            $this->warnings[] = $message;
        }
    }

    private function addError(string $message): void
    {
        $this->errorCount++;

        if (count($this->errors) < self::MAX_MESSAGES) {
            $this->errors[] = $message;
        }
    }

    /**
     * The database's own short message. QueryException's full text carries the
     * row's values, which would put customer data in the report.
     */
    private function reason(Throwable $exception): string
    {
        return $exception instanceof QueryException
            ? (string) ($exception->errorInfo[2] ?? 'The database refused this row.')
            : $exception->getMessage();
    }
}
