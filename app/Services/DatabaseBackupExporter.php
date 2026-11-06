<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DatabaseBackupFormat;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-008: writes the shop's data as a DatabaseBackupFormat file, a chunk at a
 * time so the size of the database never decides the memory used.
 */
class DatabaseBackupExporter
{
    private const int ROWS_PER_QUERY = 1000;

    /**
     * @param  Closure(string): void  $write  Receives each line, with its newline.
     */
    public function export(Closure $write): void
    {
        $this->inConsistentSnapshot(function () use ($write): void {
            $write(DatabaseBackupFormat::HEADER."\n");
            $write(DatabaseBackupFormat::VERSION_PREFIX.DatabaseBackupFormat::VERSION."\n");
            $write(DatabaseBackupFormat::MIGRATION_PREFIX.DatabaseBackupFormat::latestMigration()."\n");
            $write('-- created: '.now()->toIso8601String()."\n");
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

            $rows = 0;

            foreach (DatabaseBackupFormat::tables() as $table) {
                $rows += $this->exportTable($table, $write);
            }

            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            $write(DatabaseBackupFormat::FOOTER_PREFIX.$rows."\n");
        });
    }

    /**
     * Production runs READ-COMMITTED, where an order placed mid-download could
     * appear without its customer. Nothing is written, so it always rolls back.
     */
    private function inConsistentSnapshot(Closure $callback): void
    {
        if (DB::transactionLevel() === 0 && in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        DB::beginTransaction();

        try {
            $callback();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  Closure(string): void  $write
     */
    private function exportTable(string $table, Closure $write): int
    {
        $columns = DatabaseBackupFormat::columns($table);
        $query = DB::table($table)->select($columns);

        foreach ($this->orderColumns($table, $columns) as $column) {
            $query->orderBy($column);
        }

        $rows = 0;

        for ($page = 1; ; $page++) {
            $chunk = (clone $query)->forPage($page, self::ROWS_PER_QUERY)->get();

            foreach ($chunk as $row) {
                $write(DatabaseBackupFormat::encodeRow($table, $columns, array_values((array) $row))."\n");
            }

            $rows += $chunk->count();

            if ($chunk->count() < self::ROWS_PER_QUERY) {
                return $rows;
            }
        }
    }

    /**
     * Stable order, or paging could repeat or miss rows.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function orderColumns(string $table, array $columns): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return $columns;
    }
}
