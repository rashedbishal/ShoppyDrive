<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * OPS-008: the data-only .sql backup format, one row per line.
 *
 * A file is valid MySQL, so phpMyAdmin can import it if the site is down, but
 * the app never executes one. Uploads are read by parseRow(), which only
 * accepts the exact line shape encodeRow() writes and returns plain values
 * for parameter binding, so a line carrying any other SQL is rejected.
 */
class DatabaseBackupFormat
{
    public const string HEADER = '-- ShoppyDrive data backup';

    public const int VERSION = 1;

    public const string VERSION_PREFIX = '-- format: ';

    public const string MIGRATION_PREFIX = '-- latest migration: ';

    public const string FOOTER_PREFIX = '-- end of backup, rows: ';

    /** @var list<string> Lines allowed around the data, which do nothing here. */
    public const array SETUP_LINES = ['SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS=0;', 'SET FOREIGN_KEY_CHECKS=1;'];

    /**
     * Temporary or framework-owned: restoring these would bring back old
     * logins and half-finished jobs, and `migrations` describes the code
     * rather than the shop's data.
     *
     * @var list<string>
     */
    public const array EXCLUDED_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions',
    ];

    /**
     * Permanent records (PAY-RETURN-001, DB-001): a restore may add a missing
     * row but never rewrites an existing one.
     *
     * @var list<string>
     */
    public const array APPEND_ONLY_TABLES = ['courier_delivery_evidence', 'order_deletion_audits'];

    /**
     * Every table a backup covers.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        $tables = Schema::getTableListing(Schema::getCurrentSchemaListing(), schemaQualified: false);

        return array_values(array_filter($tables, fn (string $table): bool => ! in_array($table, self::EXCLUDED_TABLES, true)
            && ! Str::startsWith($table, 'sqlite_')));
    }

    /**
     * The real, stored columns of a table.
     *
     * @return list<string>
     */
    public static function columns(string $table): array
    {
        $columns = array_filter(Schema::getColumns($table), fn (array $column): bool => empty($column['generation']));

        return array_values(array_column($columns, 'name'));
    }

    public static function latestMigration(): ?string
    {
        return DB::table('migrations')->orderByDesc('id')->value('migration');
    }

    /**
     * @param  list<string>  $columns
     * @param  array<int, mixed>  $values
     */
    public static function encodeRow(string $table, array $columns, array $values): string
    {
        $quotedColumns = implode(',', array_map(fn (string $column): string => "`{$column}`", $columns));
        $encodedValues = implode(',', array_map(self::encodeValue(...), $values));

        return "INSERT INTO `{$table}` ({$quotedColumns}) VALUES ({$encodedValues}".self::updateClause($columns);
    }

    /**
     * @return array{table: string, columns: list<string>, values: list<string|null>}|null Null for any other line.
     */
    public static function parseRow(string $line): ?array
    {
        if (preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)` \(((?:`[a-zA-Z0-9_]+`,?)+)\) VALUES \(/', $line, $head) !== 1) {
            return null;
        }

        $columns = explode(',', str_replace('`', '', $head[2]));
        $values = self::parseValues($line, strlen($head[0]), $end);

        if ($values === null || count($values) !== count($columns) || substr($line, $end) !== self::updateClause($columns)) {
            return null;
        }

        return ['table' => $head[1], 'columns' => $columns, 'values' => $values];
    }

    /**
     * @param  list<string>  $columns
     */
    private static function updateClause(array $columns): string
    {
        $updates = implode(',', array_map(fn (string $column): string => "`{$column}`=VALUES(`{$column}`)", $columns));

        return ") ON DUPLICATE KEY UPDATE {$updates};";
    }

    private static function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        if (! mb_check_encoding($text, 'UTF-8')) {
            return "X'".bin2hex($text)."'";
        }

        return "'".strtr($text, ['\\' => '\\\\', "'" => "''", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r'])."'";
    }

    /**
     * Reads comma-separated values up to the closing bracket, setting $end to
     * that bracket's offset.
     *
     * @return list<string|null>|null
     */
    private static function parseValues(string $line, int $offset, ?int &$end): ?array
    {
        $values = [];
        $length = strlen($line);

        while ($offset < $length) {
            $value = self::parseValue($line, $offset);

            if ($value === false) {
                return null;
            }

            $values[] = $value;
            $next = $line[$offset] ?? '';

            if ($next === ')') {
                $end = $offset;

                return $values;
            }

            if ($next !== ',') {
                return null;
            }

            $offset++;
        }

        return null;
    }

    /**
     * One value starting at $offset, which moves past it. False if invalid.
     */
    private static function parseValue(string $line, int &$offset): string|false|null
    {
        if (substr_compare($line, 'NULL', $offset, 4) === 0) {
            $offset += 4;

            return null;
        }

        if (preg_match('/\G-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $line, $number, 0, $offset) === 1) {
            $offset += strlen($number[0]);

            return $number[0];
        }

        if (preg_match("/\GX'((?:[0-9a-f]{2})*)'/", $line, $hex, 0, $offset) === 1) {
            $offset += strlen($hex[0]);

            return (string) hex2bin($hex[1]);
        }

        return ($line[$offset] ?? '') === "'" ? self::parseString($line, $offset) : false;
    }

    private static function parseString(string $line, int &$offset): string|false
    {
        $text = '';
        $length = strlen($line);

        for ($position = $offset + 1; $position < $length; $position++) {
            $character = $line[$position];

            if ($character === '\\') {
                $escape = $line[++$position] ?? '';
                $decoded = ['\\' => '\\', '0' => "\0", 'n' => "\n", 'r' => "\r"][$escape] ?? null;

                if ($decoded === null) {
                    return false;
                }

                $text .= $decoded;
            } elseif ($character !== "'") {
                $text .= $character;
            } elseif (($line[$position + 1] ?? '') === "'") {
                $text .= "'";
                $position++;
            } else {
                $offset = $position + 1;

                return $text;
            }
        }

        return false;
    }
}
