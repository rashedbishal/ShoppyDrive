<?php

declare(strict_types=1);

/*
 * BE-011: the admin dashboard shows when every Pathao status check is
 * failing, to admins who can see orders.
 */

use App\Console\Commands\SyncPathaoOrderStatuses;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function flagPathaoProblem(int $minutesAgo): void
{
    Cache::forever(SyncPathaoOrderStatuses::PROBLEM_CACHE_KEY, [
        'since' => now()->subMinutes($minutesAgo)->getTimestamp(),
        'last_failed_at' => now()->getTimestamp(),
        'last_error' => 'Unauthenticated.',
    ]);
}

test('the dashboard shows how long Pathao has been failing and the last error', function () {
    flagPathaoProblem(90);

    actingAs(createMotherAdmin(), 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pathao_sync_problem.failing_for_seconds', fn (int $s) => $s >= 5400 && $s < 5460)
            ->where('stats.pathao_sync_problem.last_error', 'Unauthenticated.'));
});

test('no banner data when nothing is failing, or for an admin who cannot see orders', function () {
    actingAs(createMotherAdmin(), 'admin')->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.pathao_sync_problem', null));

    flagPathaoProblem(10);
    $staff = Admin::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => bcrypt('password')]);
    $staff->forceFill(['permissions' => ['products' => ['view']]])->save();

    actingAs($staff, 'admin')->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.pathao_sync_problem', null));
});

test('the problem record survives the real file cache store (plain values only)', function () {
    $directory = sys_get_temp_dir().'/pathao-problem-test-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory]);
    Cache::purge('file');

    try {
        flagPathaoProblem(5);

        actingAs(createMotherAdmin(), 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.pathao_sync_problem.last_error', 'Unauthenticated.'));
    } finally {
        File::deleteDirectory($directory);
    }
});
