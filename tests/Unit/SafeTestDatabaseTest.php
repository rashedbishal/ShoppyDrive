<?php

declare(strict_types=1);

use Tests\SafeTestDatabase;

test('SQLite is always allowed', function () {
    SafeTestDatabase::assertSafe('sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
})->throwsNoExceptions();

test('MySQL or MariaDB is allowed only for a shoppydrive_test_ database', function (string $driver) {
    SafeTestDatabase::assertSafe('mysql', ['driver' => $driver, 'database' => 'shoppydrive_test_loyalty']);
})->with(['mysql', 'mariadb'])->throwsNoExceptions();

test('any other MySQL or MariaDB database is refused', function (string $driver, string $database) {
    expect(fn () => SafeTestDatabase::assertSafe('mysql', ['driver' => $driver, 'database' => $database]))
        ->toThrow(RuntimeException::class, "database \"{$database}\"");
})->with([
    'the development database' => ['mysql', 'ShoppyDrive'],
    'missing the trailing underscore' => ['mysql', 'shoppydrive_test'],
    'prefix in the wrong place' => ['mariadb', 'prod_shoppydrive_test_x'],
    'empty' => ['mysql', ''],
]);

test('the database named in a connection URL wins over the database key', function () {
    expect(fn () => SafeTestDatabase::assertSafe('mysql', [
        'driver' => 'mysql',
        'database' => 'shoppydrive_test_loyalty',
        'url' => 'mysql://user:secret@127.0.0.1:3306/ShoppyDrive',
    ]))->toThrow(RuntimeException::class, 'database "ShoppyDrive"');
});

test('other drivers are refused', function () {
    expect(fn () => SafeTestDatabase::assertSafe('pgsql', ['driver' => 'pgsql', 'database' => 'shoppydrive_test_x']))
        ->toThrow(RuntimeException::class, 'driver "pgsql"');
});
