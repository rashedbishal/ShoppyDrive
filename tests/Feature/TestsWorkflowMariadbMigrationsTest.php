<?php

declare(strict_types=1);

/*
 * INFRA-003: every migration must run on real MariaDB in CI before
 * production's `migrate --force` is its first MariaDB run. Guards against
 * the job being dropped, pointed at a non-disposable database, or drifting
 * away from production's MariaDB version.
 */

use Symfony\Component\Yaml\Yaml;
use Tests\SafeTestDatabase;

function mariadbMigrationsJob(): array
{
    $workflow = Yaml::parseFile(base_path('.github/workflows/tests.yml'));

    return $workflow['jobs']['mariadb-migrations'] ?? [];
}

test('the tests workflow has a MariaDB job on the same version line as production', function () {
    expect(mariadbMigrationsJob()['services']['mariadb']['image'] ?? null)->toBe('mariadb:11.8');
});

test('the MariaDB job only ever targets a disposable test database', function () {
    $job = mariadbMigrationsJob();

    expect($job['env']['DB_DATABASE'])->toStartWith(SafeTestDatabase::REQUIRED_PREFIX)
        ->and($job['services']['mariadb']['env']['MARIADB_DATABASE'])->toBe($job['env']['DB_DATABASE'])
        ->and($job['env']['DB_HOST'])->toBe('127.0.0.1');
});

test('the MariaDB job runs the same migrate command production runs, then checks nothing is left', function () {
    $commands = collect(mariadbMigrationsJob()['steps'])->pluck('run')->filter()->implode("\n");

    expect($commands)->toContain('php artisan migrate --force')
        ->toContain('Nothing to migrate');
});
