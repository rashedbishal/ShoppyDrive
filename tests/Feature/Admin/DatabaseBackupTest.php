<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Models\Brand;
use App\Models\Order;
use App\Models\User;
use App\Services\DatabaseBackupExporter;
use App\Support\DatabaseBackupFormat;
use App\Support\MotherPasswordGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function makeBackup(): string
{
    $contents = '';
    app(DatabaseBackupExporter::class)->export(function (string $line) use (&$contents): void {
        $contents .= $line;
    });

    return $contents;
}

/**
 * A valid file holding only these rows.
 *
 * @param  list<string>  $rows
 */
function backupWith(array $rows, ?int $declaredRows = null): string
{
    return implode("\n", [
        DatabaseBackupFormat::HEADER,
        DatabaseBackupFormat::VERSION_PREFIX.DatabaseBackupFormat::VERSION,
        DatabaseBackupFormat::MIGRATION_PREFIX.DatabaseBackupFormat::latestMigration(),
        ...$rows,
        DatabaseBackupFormat::FOOTER_PREFIX.($declaredRows ?? count($rows)),
    ])."\n";
}

function inspectBackup(string $contents)
{
    return test()->post(route('admin.database-backup.inspect'), [
        'backup' => UploadedFile::fake()->createWithContent('backup.sql', $contents),
    ]);
}

/**
 * @return array<string, mixed>
 */
function pendingReport(): array
{
    return session('database_restore.pending')['report'];
}

function tableReport(string $table): array
{
    return collect(pendingReport()['tables'])->firstWhere('table', $table);
}

beforeEach(function () {
    Storage::fake('local');

    $this->mother = createAdmin();
    $this->mother->forceFill(['is_mother' => true])->save();
    actingAs($this->mother, 'admin');
});

test('only the mother admin can reach any backup action', function (string $method, string $routeName) {
    $this->app['auth']->forgetGuards();
    actingAs(createAdmin(), 'admin');

    $this->{$method}(route($routeName), ['password' => 'password'])->assertForbidden();
})->with([
    ['get', 'admin.database-backup.index'],
    ['post', 'admin.database-backup.download'],
    ['post', 'admin.database-backup.inspect'],
    ['post', 'admin.database-backup.apply'],
    ['delete', 'admin.database-backup.discard'],
]);

test('a backup needs the password, and a wrong one downloads nothing', function () {
    $this->post(route('admin.database-backup.download'), ['password' => 'wrong'])
        ->assertRedirect()
        ->assertSessionHasErrors('password');

    expect(Activity::query()->where('event', 'downloaded')->exists())->toBeFalse();
});

test('repeated wrong passwords lock the backup', function () {
    foreach (range(1, MotherPasswordGate::MAX_FAILURES) as $attempt) {
        $this->post(route('admin.database-backup.download'), ['password' => 'wrong']);
    }

    $this->post(route('admin.database-backup.download'), ['password' => 'password'])
        ->assertSessionHasErrors('password');
});

test('the download holds shop data as data only, leaves out temporary tables and is logged', function () {
    Brand::factory()->create(['name' => 'Acme']);

    $response = $this->post(route('admin.database-backup.download'), ['password' => 'password']);
    $contents = $response->assertOk()->streamedContent();

    expect($response->headers->get('Content-Disposition'))->toContain('.sql')
        ->and($contents)->toStartWith(DatabaseBackupFormat::HEADER)
        ->toContain('INSERT INTO `brands`')->toContain("'Acme'")
        ->toContain(DatabaseBackupFormat::FOOTER_PREFIX)
        ->not->toContain('CREATE TABLE')->not->toContain('DROP TABLE')
        ->not->toContain('INSERT INTO `migrations`')->not->toContain('INSERT INTO `sessions`')
        ->and(Activity::query()->where('event', 'downloaded')->count())->toBe(1);
});

test('tricky values survive the file format unchanged', function (mixed $value, ?string $expected) {
    $line = DatabaseBackupFormat::encodeRow('brands', ['id', 'name'], [1, $value]);

    expect($line)->not->toContain("\n")
        ->and(DatabaseBackupFormat::parseRow($line))->toBe([
            'table' => 'brands', 'columns' => ['id', 'name'], 'values' => ['1', $expected],
        ]);
})->with([
    'apostrophe' => ["O'Reilly", "O'Reilly"],
    'backslash' => ['C:\\new', 'C:\\new'],
    'ending in a backslash' => ['end\\', 'end\\'],
    'line breaks' => ["one\ntwo\r\nthree", "one\ntwo\r\nthree"],
    'bangla' => ['শপি ড্রাইভ ৳', 'শপি ড্রাইভ ৳'],
    'looks like sql' => ["'); DROP TABLE users; --", "'); DROP TABLE users; --"],
    'nothing' => [null, null],
    'empty text' => ['', ''],
    'the word NULL' => ['NULL', 'NULL'],
    'a decimal' => [12.5, '12.5'],
    'invalid utf-8' => ["\xFF\xFE", "\xFF\xFE"],
]);

test('any line that is not exactly a backup row is refused', function (string $line) {
    expect(DatabaseBackupFormat::parseRow($line))->toBeNull();
})->with([
    'another statement' => ['DROP TABLE users;'],
    'delete' => ['DELETE FROM users;'],
    'statement added after a row' => ['INSERT INTO `brands` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`); DROP TABLE users;'],
    'row without the update clause' => ['INSERT INTO `brands` (`id`) VALUES (1);'],
    'a subquery as a value' => ['INSERT INTO `brands` (`id`) VALUES ((SELECT password FROM admins)) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);'],
    'a function as a value' => ['INSERT INTO `brands` (`id`) VALUES (SLEEP(10)) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);'],
    'an update clause setting something else' => ["INSERT INTO `admins` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `password`='x';"],
    'a table name breaking out' => ['INSERT INTO `brands`; DROP TABLE users; --` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);'],
    'more columns than values' => ['INSERT INTO `brands` (`id`,`name`) VALUES (1) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`),`name`=VALUES(`name`);'],
    'an unclosed string' => ["INSERT INTO `brands` (`id`) VALUES ('abc) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`);"],
]);

test('checking a file changes nothing, and the report says what would happen', function () {
    $kept = Brand::factory()->create(['name' => 'Same']);
    $edited = Brand::factory()->create(['name' => 'Original']);
    $deleted = Brand::factory()->create(['name' => 'Deleted']);
    $backup = makeBackup();

    Brand::whereKey($edited->id)->update(['name' => 'Changed']);
    DB::table('brands')->where('id', $deleted->id)->delete();
    $newer = Brand::factory()->create(['name' => 'Newer']);

    inspectBackup($backup)->assertRedirect(route('admin.database-backup.index'));

    expect(pendingReport()['ok'])->toBeTrue()
        ->and(tableReport('brands'))->toMatchArray(['added' => 1, 'updated' => 1, 'skipped' => 1, 'conflicts' => 0])
        ->and(Brand::find($deleted->id))->toBeNull()
        ->and(Brand::find($edited->id)->name)->toBe('Changed')
        ->and(Brand::find($newer->id))->not->toBeNull()
        ->and(Brand::find($kept->id))->not->toBeNull();

    $this->get(route('admin.database-backup.index'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/database-backup/index')
        ->where('pendingReport.ok', true));
});

test('restoring adds missing rows, updates changed ones, keeps newer ones and removes the upload', function () {
    Brand::factory()->create(['name' => 'Same']);
    $edited = Brand::factory()->create(['name' => 'Original']);
    $deleted = Brand::factory()->create(['name' => 'Deleted']);
    $backup = makeBackup();

    Brand::whereKey($edited->id)->update(['name' => 'Changed']);
    DB::table('brands')->where('id', $deleted->id)->delete();
    $newer = Brand::factory()->create(['name' => 'Newer']);
    $countBeforeRestore = Brand::count();

    inspectBackup($backup);
    $this->post(route('admin.database-backup.apply'), ['password' => 'password'])
        ->assertRedirect(route('admin.database-backup.index'));

    expect(Brand::find($edited->id)->name)->toBe('Original')
        ->and(Brand::find($deleted->id)->name)->toBe('Deleted')
        ->and(Brand::find($newer->id)->name)->toBe('Newer')
        ->and(Brand::count())->toBe($countBeforeRestore + 1)
        ->and(session('database_restore.pending'))->toBeNull()
        ->and(Storage::disk('local')->files(DatabaseBackupController::UPLOAD_DIRECTORY))->toBeEmpty()
        ->and(Activity::query()->where('event', 'restored')->count())->toBe(1);

    $this->get(route('admin.database-backup.index'))
        ->assertInertia(fn (Assert $page) => $page->where('restoreResult.committed', true));
});

test('restoring the same file twice only skips the second time', function () {
    Brand::factory()->count(2)->create();
    $backup = makeBackup();

    inspectBackup($backup);

    expect(tableReport('brands'))->toMatchArray(['added' => 0, 'updated' => 0, 'skipped' => 2]);
});

test('restoring sends no customer email', function () {
    Mail::fake();
    $order = Order::create([
        'order_number' => 'ORD-RESTORE-1', 'user_id' => User::factory()->create()->id, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);
    $backup = makeBackup();
    Mail::forgetMailers();
    Mail::fake();
    DB::table('orders')->where('id', $order->id)->delete();

    inspectBackup($backup);
    $this->post(route('admin.database-backup.apply'), ['password' => 'password']);

    expect(DB::table('orders')->where('id', $order->id)->exists())->toBeTrue();
    Mail::assertNothingOutgoing();
});

test('a restore clears cached data so the site shows what was restored', function () {
    $backup = makeBackup();
    Cache::put('stale-key', 'stale');

    inspectBackup($backup);
    expect(Cache::get('stale-key'))->toBe('stale');

    $this->post(route('admin.database-backup.apply'), ['password' => 'password']);
    expect(Cache::get('stale-key'))->toBeNull();
});

test('a file with any problem is refused whole', function (Closure $makeContents, string $expectedMessage) {
    $brand = Brand::factory()->create(['name' => 'Untouched']);

    inspectBackup($makeContents($brand));

    expect(pendingReport()['ok'])->toBeFalse()
        ->and(collect(pendingReport()['errors'])->join(' '))->toContain($expectedMessage)
        ->and(Brand::find($brand->id)->name)->toBe('Untouched');

    $this->post(route('admin.database-backup.apply'), ['password' => 'password']);

    expect(Brand::find($brand->id)->name)->toBe('Untouched')
        ->and(session('database_restore.result')['committed'])->toBeFalse();
})->with([
    'not a backup' => [fn () => "hello\n", 'not a ShoppyDrive backup'],
    'hidden statement' => [fn () => backupWith(['DROP TABLE users;']), 'not a data row'],
    'cut off download' => [fn () => DatabaseBackupFormat::HEADER."\n", 'incomplete'],
    'rows lost' => [fn (Brand $brand) => backupWith([DatabaseBackupFormat::encodeRow('brands', ['id', 'name'], [$brand->id, 'Hacked'])], 5), 'should hold 5 rows'],
    'unknown table' => [fn () => backupWith([DatabaseBackupFormat::encodeRow('no_such_table', ['id'], [1])]), 'no_such_table'],
    'unknown column' => [fn (Brand $brand) => backupWith([DatabaseBackupFormat::encodeRow('brands', ['id', 'not_a_column'], [$brand->id, 'x'])]), 'not_a_column'],
    'protected table' => [fn () => backupWith([DatabaseBackupFormat::encodeRow('sessions', ['id'], ['abc'])]), 'sessions'],
    'migrations table' => [fn () => backupWith([DatabaseBackupFormat::encodeRow('migrations', ['id'], [1])]), 'migrations'],
    'required value missing' => [fn () => backupWith([
        DatabaseBackupFormat::encodeRow('brands', ['id', 'name'], [999, null]),
        DatabaseBackupFormat::encodeRow('brands', ['id', 'name'], [1000, 'Fine']),
    ]), 'brands'],
    'content after the end' => [fn () => backupWith([])."DROP TABLE users;\n", 'after the end'],
    'wrong format version' => [fn () => str_replace(DatabaseBackupFormat::VERSION_PREFIX.DatabaseBackupFormat::VERSION, DatabaseBackupFormat::VERSION_PREFIX.'99', backupWith([])), 'cannot read'],
]);

test('a row pointing at a record that does not exist is refused', function () {
    $user = User::factory()->create();
    $line = DatabaseBackupFormat::encodeRow('orders', ['id', 'user_id'], [1, 999999]);

    inspectBackup(backupWith([$line]));

    expect(pendingReport()['ok'])->toBeFalse()
        ->and($user->fresh())->not->toBeNull()
        ->and(DB::table('orders')->count())->toBe(0);
});

test('a row repeating a unique value held by a different row is skipped, not duplicated', function () {
    $existing = Brand::factory()->create(['slug' => 'acme']);
    $backup = Brand::factory()->create(['slug' => 'other']);
    $columns = DatabaseBackupFormat::columns('brands');
    $row = array_values((array) DB::table('brands')->select($columns)->find($backup->id));
    $row[array_search('slug', $columns, true)] = 'acme';
    $row[array_search('id', $columns, true)] = 5000;

    inspectBackup(backupWith([DatabaseBackupFormat::encodeRow('brands', $columns, $row)]));

    expect(pendingReport()['ok'])->toBeTrue()
        ->and(tableReport('brands')['conflicts'])->toBe(1)
        ->and(pendingReport()['warnings'][0])->toContain('unique value');

    $this->post(route('admin.database-backup.apply'), ['password' => 'password']);

    expect(Brand::where('slug', 'acme')->pluck('id')->all())->toBe([$existing->id]);
});

test('permanent records can be added back but never rewritten', function () {
    DB::table('order_deletion_audits')->insert([
        'id' => 1, 'order_id' => 7, 'order_number' => 'SD-1', 'step' => 'confirm', 'outcome' => 'original', 'created_at' => now(),
    ]);
    $line = DatabaseBackupFormat::encodeRow('order_deletion_audits', ['id', 'order_id', 'order_number', 'step', 'outcome'], [1, 7, 'SD-1', 'confirm', 'tampered']);

    inspectBackup(backupWith([$line]));

    expect(pendingReport()['ok'])->toBeTrue()
        ->and(tableReport('order_deletion_audits')['conflicts'])->toBe(1);

    $this->post(route('admin.database-backup.apply'), ['password' => 'password']);

    expect(DB::table('order_deletion_audits')->where('id', 1)->value('outcome'))->toBe('original');
});

test('applying needs the password and leaves the check waiting if it is wrong', function () {
    $brand = Brand::factory()->create(['name' => 'Original']);
    $backup = makeBackup();
    Brand::whereKey($brand->id)->update(['name' => 'Changed']);
    inspectBackup($backup);

    $this->post(route('admin.database-backup.apply'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(Brand::find($brand->id)->name)->toBe('Changed')
        ->and(session('database_restore.pending'))->not->toBeNull();
});

test('a check expires, and an expired one cannot be applied', function () {
    $brand = Brand::factory()->create(['name' => 'Original']);
    $backup = makeBackup();
    Brand::whereKey($brand->id)->update(['name' => 'Changed']);
    inspectBackup($backup);

    $this->travel(31)->minutes();
    $this->post(route('admin.database-backup.apply'), ['password' => 'password'])->assertSessionHas('error');

    expect(Brand::find($brand->id)->name)->toBe('Changed')
        ->and(Storage::disk('local')->files(DatabaseBackupController::UPLOAD_DIRECTORY))->toBeEmpty();
});

test('cancelling removes the uploaded copy of the data', function () {
    inspectBackup(makeBackup());
    expect(Storage::disk('local')->files(DatabaseBackupController::UPLOAD_DIRECTORY))->toHaveCount(1);

    $this->delete(route('admin.database-backup.discard'));

    expect(Storage::disk('local')->files(DatabaseBackupController::UPLOAD_DIRECTORY))->toBeEmpty();
});

test('only .sql files can be uploaded', function () {
    $this->post(route('admin.database-backup.inspect'), [
        'backup' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
    ])->assertSessionHasErrors('backup');
});

test('the nightly prune removes uploads nobody confirmed', function () {
    $disk = Storage::disk('local');
    $disk->put(DatabaseBackupController::UPLOAD_DIRECTORY.'/old.sql', 'data');
    touch($disk->path(DatabaseBackupController::UPLOAD_DIRECTORY.'/old.sql'), now()->subDays(2)->timestamp);
    $disk->put(DatabaseBackupController::UPLOAD_DIRECTORY.'/recent.sql', 'data');

    $this->artisan('data:prune')->assertSuccessful();

    $disk->assertMissing(DatabaseBackupController::UPLOAD_DIRECTORY.'/old.sql');
    $disk->assertExists(DatabaseBackupController::UPLOAD_DIRECTORY.'/recent.sql');
});
