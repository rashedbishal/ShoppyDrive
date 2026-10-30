<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/**
 * Proves Tests\TestCase::setUpTraits() stops a misconfigured MySQL run
 * before RefreshDatabase touches anything. Each case runs a real Feature
 * test in a child process pointed at port 1 on localhost, where nothing
 * listens — so even a broken guard could never reach a real database.
 */
function runFeatureTestAgainstMysql(string $database): string
{
    $result = Process::path(base_path())->env([
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => $database,
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '1',
        'DB_URL' => '',
    ])->timeout(120)->run([PHP_BINARY, 'vendor/bin/pest', 'tests/Feature/Auth/PasswordResetTest.php', '--filter=reset password link screen can be rendered']);

    expect($result->successful())->toBeFalse();

    return $result->output().$result->errorOutput();
}

it('refuses a non-test MySQL database before RefreshDatabase tries to connect', function () {
    $output = runFeatureTestAgainstMysql('ShoppyDrive');

    expect($output)->toContain('Refusing to run tests against database connection [mysql]')
        ->toContain('ShoppyDrive')
        ->not->toContain('[2002]');
});

it('lets a shoppydrive_test_ MySQL database past the guard, to RefreshDatabase', function () {
    // Past the guard, RefreshDatabase's first connection attempt fails on the
    // dead port — showing the guard runs first and only blocks what it should.
    $output = runFeatureTestAgainstMysql('shoppydrive_test_guard_probe');

    expect($output)->toContain('[2002]')
        ->not->toContain('Refusing to run tests');
});
