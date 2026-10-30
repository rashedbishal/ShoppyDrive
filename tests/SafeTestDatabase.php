<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

/**
 * Refuses to let the test suite touch any database that isn't clearly
 * disposable. RefreshDatabase runs `migrate:fresh` (dropping every table)
 * on a non-in-memory connection, so a run accidentally pointed at a real
 * MySQL/MariaDB database would wipe it. Checked from TestCase::setUpTraits(),
 * i.e. before RefreshDatabase / DatabaseMigrations / DatabaseTruncation run.
 *
 * Allowed: SQLite (the suite's default, in-memory), and MySQL/MariaDB only
 * when the effective database name starts with `shoppydrive_test_`. The
 * effective name is resolved from the connection config itself — including
 * a `url` (DB_URL), which overrides the `database` key — not from any
 * environment variable a test happens to check.
 */
final class SafeTestDatabase
{
    public const REQUIRED_PREFIX = 'shoppydrive_test_';

    /**
     * @param  array<string, mixed>  $connection
     */
    public static function assertSafe(string $name, array $connection): void
    {
        $effective = (new ConfigurationUrlParser)->parseConfiguration($connection);
        $driver = (string) ($effective['driver'] ?? '');
        $database = (string) ($effective['database'] ?? '');

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true) && str_starts_with($database, self::REQUIRED_PREFIX)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests against database connection [%s] (driver "%s", database "%s"): tests may only use SQLite or a MySQL/MariaDB database whose name starts with "%s". Nothing was migrated or modified.',
            $name,
            $driver,
            $database,
            self::REQUIRED_PREFIX,
        ));
    }
}
