<?php

declare(strict_types=1);

/*
 * DB-004 retest: expired cache rows/files were only cleaned up once a day,
 * bundled inside data:prune — but rate-limit counters (the database cache
 * store's main occupant) can expire within minutes, so the table could
 * still grow meaningfully between nightly runs. Split out into its own
 * command, scheduled hourly.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('expired cache rows are removed and live ones stay (DB-004)', function () {
    DB::table('cache')->insert([
        ['key' => 'expired-limit', 'value' => 'x', 'expiration' => now()->subMinute()->timestamp],
        ['key' => 'live-limit', 'value' => 'x', 'expiration' => now()->addHour()->timestamp],
    ]);

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(DB::table('cache')->pluck('key')->all())->toBe(['live-limit']);
});

test('more expired cache rows than one batch are all removed in a single run', function () {
    $rows = array_map(fn (int $n) => [
        'key' => "expired-{$n}", 'value' => 'x', 'expiration' => now()->subMinute()->timestamp,
    ], range(1, 501));
    DB::table('cache')->insert($rows);

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(DB::table('cache')->count())->toBe(0);
});

test('expired cache files are removed, and live, forever and foreign files stay (PERF-008)', function () {
    $directory = storage_path('framework/testing/cache-'.Str::random(8));
    File::ensureDirectoryExists($directory.'/ab/cd');
    config(['cache.stores.file.path' => $directory]);

    $files = [
        'expired' => [$directory.'/ab/cd/expired', now()->subMinute()->timestamp.'data'],
        'live' => [$directory.'/ab/cd/live', now()->addHour()->timestamp.'data'],
        'forever' => [$directory.'/ab/cd/forever', '9999999999data'],
        'foreign' => [$directory.'/ab/.gitignore', "*\n!.gitignore\n"],
    ];

    foreach ($files as [$path, $contents]) {
        file_put_contents($path, $contents);
    }

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(file_exists($files['expired'][0]))->toBeFalse()
        ->and(file_exists($files['live'][0]))->toBeTrue()
        ->and(file_exists($files['forever'][0]))->toBeTrue()
        ->and(file_exists($files['foreign'][0]))->toBeTrue();

    File::deleteDirectory($directory);
});
