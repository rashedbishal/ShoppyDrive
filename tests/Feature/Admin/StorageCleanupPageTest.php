<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Services\StorageCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->mother = createMotherAdmin();
});

function oldPublicFile(string $path, int $daysAgo = 30): void
{
    Storage::disk('public')->put($path, 'bytes of '.$path);
    touch(Storage::disk('public')->path($path), now()->subDays($daysAgo)->getTimestamp());
}

/** A quarantine batch moved $daysAgo days ago, holding one file. */
function quarantineBatch(string $batch, int $daysAgo, string $file = 'brands/old.webp'): void
{
    $local = Storage::disk('local');
    $directory = StorageCleanupService::QUARANTINE_DIRECTORY.'/'.$batch;
    $local->put($directory.'/'.$file, 'bytes of '.$file);
    $local->put($directory.'/manifest.json', json_encode(['files' => [$file]]));
    touch($local->path($directory.'/manifest.json'), now()->subDays($daysAgo)->getTimestamp());
}

test('the page shows the report and the batches', function () {
    oldPublicFile('brands/unused.webp');
    oldPublicFile('brands/in-use.webp');
    Brand::factory()->create(['logo' => '/storage/brands/in-use.webp']);
    quarantineBatch('2026-01-01_000000', daysAgo: 45);

    actingAs($this->mother, 'admin')
        ->get(route('admin.storage-cleanup.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/storage-cleanup/index')
            ->where('report.totals.files', 2)
            ->where('report.totals.unused', 1)
            ->where('report.unused.0.path', 'brands/unused.webp')
            ->where('batches.0.batch', '2026-01-01_000000')
            ->where('batches.0.files', 1)
            ->where('batches.0.purgeable', true)
            ->where('purgeAfterDays', 30));
});

test('moving unused files aside quarantines them and is audited', function () {
    oldPublicFile('brands/unused.webp');
    oldPublicFile('brands/in-use.webp');
    Brand::factory()->create(['logo' => '/storage/brands/in-use.webp']);

    actingAs($this->mother, 'admin')
        ->post(route('admin.storage-cleanup.quarantine'), ['confirmed' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Storage::disk('public')->exists('brands/unused.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists('brands/in-use.webp'))->toBeTrue()
        ->and(app(StorageCleanupService::class)->batches())->toHaveCount(1)
        ->and(Activity::where('event', 'storage.quarantined')->exists())->toBeTrue();
});

test('moving aside needs confirmation, and says so when there is nothing to move', function () {
    oldPublicFile('brands/unused.webp');

    actingAs($this->mother, 'admin')
        ->post(route('admin.storage-cleanup.quarantine'))
        ->assertSessionHasErrors('confirmed');
    expect(Storage::disk('public')->exists('brands/unused.webp'))->toBeTrue();

    Storage::disk('public')->delete('brands/unused.webp');

    actingAs($this->mother, 'admin')
        ->post(route('admin.storage-cleanup.quarantine'), ['confirmed' => true])
        ->assertSessionHas('info', 'There are no unused files to move.');
});

test('a batch can be put back', function () {
    quarantineBatch('2026-11-01_120000', daysAgo: 3);

    actingAs($this->mother, 'admin')
        ->post(route('admin.storage-cleanup.restore', '2026-11-01_120000'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Put back 1 file from batch 2026-11-01_120000.');

    expect(Storage::disk('public')->get('brands/old.webp'))->toBe('bytes of brands/old.webp')
        ->and(app(StorageCleanupService::class)->batches())->toBe([])
        ->and(Activity::where('event', 'storage.restored')->exists())->toBeTrue();
});

test('a batch can only be deleted once it is 30 days old', function () {
    quarantineBatch('2026-11-01_120000', daysAgo: 3);
    quarantineBatch('2026-01-01_000000', daysAgo: 45);

    actingAs($this->mother, 'admin')
        ->delete(route('admin.storage-cleanup.destroy', '2026-11-01_120000'))
        ->assertSessionHas('error');
    expect(Storage::disk('local')->exists(StorageCleanupService::QUARANTINE_DIRECTORY.'/2026-11-01_120000'))->toBeTrue();

    actingAs($this->mother, 'admin')
        ->delete(route('admin.storage-cleanup.destroy', '2026-01-01_000000'))
        ->assertSessionHas('success');
    expect(Storage::disk('local')->exists(StorageCleanupService::QUARANTINE_DIRECTORY.'/2026-01-01_000000'))->toBeFalse()
        ->and(Activity::where('event', 'storage.purged')->exists())->toBeTrue();
});

test('an unknown batch is a 404', function () {
    actingAs($this->mother, 'admin')->post(route('admin.storage-cleanup.restore', '2020-01-01_000000'))->assertNotFound();
    actingAs($this->mother, 'admin')->delete(route('admin.storage-cleanup.destroy', '2020-01-01_000000'))->assertNotFound();
});

test('a batch name that is not a date never reaches the page', function () {
    actingAs($this->mother, 'admin')->post('/admin/storage-cleanup/batches/..%2F..%2Fetc/restore')->assertNotFound();
});

test('admins other than the mother admin cannot use the page', function () {
    oldPublicFile('brands/unused.webp');
    quarantineBatch('2026-01-01_000000', daysAgo: 45);
    $admin = createAdmin();

    actingAs($admin, 'admin')->get(route('admin.storage-cleanup.index'))->assertForbidden();
    actingAs($admin, 'admin')->post(route('admin.storage-cleanup.quarantine'), ['confirmed' => true])->assertForbidden();
    actingAs($admin, 'admin')->post(route('admin.storage-cleanup.restore', '2026-01-01_000000'))->assertForbidden();
    actingAs($admin, 'admin')->delete(route('admin.storage-cleanup.destroy', '2026-01-01_000000'))->assertForbidden();

    expect(Storage::disk('public')->exists('brands/unused.webp'))->toBeTrue()
        ->and(Storage::disk('local')->exists(StorageCleanupService::QUARANTINE_DIRECTORY.'/2026-01-01_000000'))->toBeTrue();
});
