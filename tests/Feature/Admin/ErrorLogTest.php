<?php

declare(strict_types=1);

use App\Support\LaravelLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Every test reads and clears files in its own temporary folder, never the
 * real storage/logs (which this very test run is writing to).
 */
beforeEach(function () {
    $this->logDirectory = sys_get_temp_dir().'/shoppydrive-error-log-'.uniqid();
    File::ensureDirectoryExists($this->logDirectory);
    app()->instance(LaravelLogReader::class, new LaravelLogReader($this->logDirectory));

    $this->mother = createMotherAdmin();
});

afterEach(function () {
    File::deleteDirectory($this->logDirectory);
});

function writeLog(string $directory, string $name, string $contents): string
{
    file_put_contents($directory.'/'.$name, $contents);

    return $directory.'/'.$name;
}

function sampleLog(): string
{
    $path = base_path('app/Http/Controllers/CheckoutController.php');

    return implode("\n", [
        '[2026-11-12 10:00:00] production.INFO: Order placed {"order":"SD-1"} ',
        '[2026-11-12 10:05:00] production.WARNING: Courier slow to respond ',
        '[2026-11-12 10:10:00] production.ERROR: Stock ran out {"exception":"[object] (App\\\\Exceptions\\\\InsufficientStockException(code: 0): Stock ran out at '.$path.':612)"} ',
        '[stacktrace]',
        '#0 /var/www/app/Services/InventoryService.php(80): ...',
        '#1 {main}',
        '"} ',
        '',
    ]);
}

test('the mother admin sees log entries newest first, with exception and location', function () {
    writeLog($this->logDirectory, 'laravel.log', sampleLog());

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/error-log/index')
            ->where('file', 'laravel.log')
            ->where('truncated', false)
            ->where('levelCounts.error', 1)
            ->where('levelCounts.warning', 1)
            ->where('levelCounts.info', 1)
            ->has('entries.data', 3)
            ->where('entries.data.0.level', 'error')
            ->where('entries.data.0.message', 'Stock ran out')
            ->where('entries.data.0.exception', 'App\\Exceptions\\InsufficientStockException')
            ->where('entries.data.0.location', 'app/Http/Controllers/CheckoutController.php:612')
            ->where('entries.data.0.text', fn (string $text): bool => str_contains($text, 'InventoryService.php(80)'))
            ->where('entries.data.2.message', 'Order placed'));
});

test('entries can be filtered by level', function () {
    writeLog($this->logDirectory, 'laravel.log', sampleLog());

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index', ['level' => 'warning']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.message', 'Courier slow to respond')
            ->where('levelCounts.error', 1));
});

test('daily files are listed newest first and can be chosen', function () {
    touch(writeLog($this->logDirectory, 'laravel-2026-11-11.log', "[2026-11-11 09:00:00] production.ERROR: Yesterday\n"), strtotime('2026-11-11 23:00'));
    touch(writeLog($this->logDirectory, 'laravel-2026-11-12.log', "[2026-11-12 09:00:00] production.ERROR: Today\n"), strtotime('2026-11-12 23:00'));
    writeLog($this->logDirectory, 'browser.log', 'not a Laravel log');

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)->pluck('name')->all() === ['laravel-2026-11-12.log', 'laravel-2026-11-11.log'])
            ->where('entries.data.0.message', 'Today'));

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index', ['file' => 'laravel-2026-11-11.log']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.message', 'Yesterday'));
});

test('only the end of a large file is read, without a cut-off first entry', function () {
    $filler = str_repeat('x', 1000);
    $lines = [];

    foreach (range(1, (int) ceil(LaravelLogReader::WINDOW_BYTES / 1000) + 200) as $i) {
        $lines[] = "[2026-11-12 10:00:00] local.INFO: Entry {$i} {$filler}";
    }

    writeLog($this->logDirectory, 'laravel.log', implode("\n", $lines)."\n");

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('truncated', true)
            ->where('entries.data.0.message', fn (string $message): bool => str_starts_with($message, 'Entry '.count($lines).' '))
            ->where('entries.total', fn (int $total): bool => $total < count($lines) && $total > 4000));
});

test('a file name outside Laravel\'s logs is refused everywhere', function (string $name) {
    writeLog($this->logDirectory, 'laravel.log', sampleLog());
    writeLog($this->logDirectory, 'browser.log', 'secret');

    actingAs($this->mother, 'admin')->get(route('admin.error-log.index', ['file' => $name]))->assertSessionHasErrors('file');
    actingAs($this->mother, 'admin')->get(route('admin.error-log.download', ['file' => $name]))->assertNotFound();
    actingAs($this->mother, 'admin')->delete(route('admin.error-log.clear'), ['confirmed' => true, 'file' => $name])->assertSessionHasErrors('file');

    expect(file_get_contents($this->logDirectory.'/browser.log'))->toBe('secret');
})->with([
    'another log in the folder' => 'browser.log',
    'a path' => '../../.env',
    'a file that doesn\'t exist' => 'laravel-2020-01-01.log',
]);

test('the mother admin can download a log file', function () {
    writeLog($this->logDirectory, 'laravel.log', sampleLog());

    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.download', ['file' => 'laravel.log']))
        ->assertOk()
        ->assertDownload('laravel.log');
});

test('clearing empties the file, keeps it for new entries, and is audited', function () {
    $path = writeLog($this->logDirectory, 'laravel.log', sampleLog());

    actingAs($this->mother, 'admin')
        ->delete(route('admin.error-log.clear'), ['confirmed' => true, 'file' => 'laravel.log'])
        ->assertRedirect(route('admin.error-log.index', ['file' => 'laravel.log']))
        ->assertSessionHas('success', 'Cleared laravel.log.');

    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBe(0)
        ->and(Activity::where('description', 'Error log laravel.log cleared.')->exists())->toBeTrue();
});

test('clearing needs an explicit confirmation', function () {
    $path = writeLog($this->logDirectory, 'laravel.log', sampleLog());

    actingAs($this->mother, 'admin')
        ->delete(route('admin.error-log.clear'), ['file' => 'laravel.log'])
        ->assertSessionHasErrors('confirmed');

    expect(filesize($path))->toBeGreaterThan(0);
});

test('an empty log folder shows an empty page', function () {
    actingAs($this->mother, 'admin')
        ->get(route('admin.error-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('file', null)->has('entries.data', 0));
});

test('admins other than the mother admin cannot view, download or clear', function () {
    $path = writeLog($this->logDirectory, 'laravel.log', sampleLog());
    $admin = createAdmin();

    actingAs($admin, 'admin')->get(route('admin.error-log.index'))->assertForbidden();
    actingAs($admin, 'admin')->get(route('admin.error-log.download', ['file' => 'laravel.log']))->assertForbidden();
    actingAs($admin, 'admin')->delete(route('admin.error-log.clear'), ['confirmed' => true, 'file' => 'laravel.log'])->assertForbidden();

    expect(filesize($path))->toBeGreaterThan(0);
});

test('DISC-42: entries are ordered by their date and time, newest first, even when the file is not', function () {
    writeLog($this->logDirectory, 'laravel.log', implode("\n", [
        '[2026-11-14 10:00:00] production.ERROR: Ten o\'clock',
        '[2026-11-14 12:30:00] production.ERROR: Half past twelve',
        '[2026-11-14 09:15:00] production.WARNING: Quarter past nine',
        '[2026-11-14T11:00:00.123456+00:00] production.INFO: Eleven, ISO format',
        '[2026-11-14 12:30:00] production.ERROR: Also half past twelve, later in the file',
    ])."\n");

    actingAs($this->mother, 'admin')->get(route('admin.error-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.message', 'Also half past twelve, later in the file')
            ->where('entries.data.1.message', 'Half past twelve')
            ->where('entries.data.2.message', 'Eleven, ISO format')
            ->where('entries.data.3.message', 'Ten o\'clock')
            ->where('entries.data.4.message', 'Quarter past nine'));
});
