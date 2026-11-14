<?php

declare(strict_types=1);

/*
 * DISC-44: production logs to one laravel.log; `logs:trim` keeps it from
 * growing past its limit (instead of daily files), never deleting the file.
 */

use App\Console\Commands\TrimLogCommand;

beforeEach(function () {
    $this->logPath = sys_get_temp_dir().'/disc44-'.uniqid().'/laravel.log';
    mkdir(dirname($this->logPath));
    config(['logging.channels.single.path' => $this->logPath]);
});

afterEach(function () {
    @unlink($this->logPath);
    @rmdir(dirname($this->logPath));
});

/**
 * Writes log entries numbered 0..n until the file passes $bytes.
 */
function disc44WriteLog(string $path, int $bytes): int
{
    $entry = 0;
    $handle = fopen($path, 'w');

    while (ftell($handle) < $bytes) {
        fwrite($handle, sprintf("[2026-11-14 10:%02d:%02d] production.ERROR: Entry %d %s\n#0 /app/Foo.php(1): bar()\n", intdiv($entry, 60) % 60, $entry % 60, $entry, str_repeat('x', 200)));
        $entry++;
    }

    fclose($handle);

    return $entry - 1;
}

test('a log over the limit keeps only its newest part, starting at a whole entry', function () {
    $last = disc44WriteLog($this->logPath, TrimLogCommand::TRIM_ABOVE_BYTES + 1024);
    $inode = fileinode($this->logPath);

    $this->artisan('logs:trim')->assertSuccessful();

    clearstatcache();
    $content = (string) file_get_contents($this->logPath);

    expect(filesize($this->logPath))->toBeLessThanOrEqual(TrimLogCommand::KEEP_BYTES + 4096)
        ->and($content)->toStartWith('[2026-11-14 ')
        ->and($content)->toContain("Entry {$last} ")
        ->and($content)->not->toContain('Entry 0 ')
        // Rewritten in place: the same file, never deleted or replaced.
        ->and(fileinode($this->logPath))->toBe($inode);
});

test('a log within the limit is left untouched', function () {
    disc44WriteLog($this->logPath, 1024 * 1024);
    $before = (string) file_get_contents($this->logPath);

    $this->artisan('logs:trim')->assertSuccessful();

    expect(file_get_contents($this->logPath))->toBe($before);
});

test('a missing log is not created', function () {
    $this->artisan('logs:trim')->assertSuccessful();

    expect(file_exists($this->logPath))->toBeFalse();
});
