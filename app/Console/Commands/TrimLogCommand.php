<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * DISC-44: production logs to one file, storage/logs/laravel.log, instead of
 * daily files (owner decision 2026-11-14). Daily rotation used to cap its size
 * (OPS-007); this does instead: once the file is over TRIM_ABOVE_BYTES, keep
 * only its newest KEEP_BYTES, starting at a whole log entry.
 *
 * The file is rewritten in place and never deleted or replaced, so processes
 * already appending to it carry on writing into it. Admin › Error Log "Clear
 * Log" still empties it on demand.
 */
class TrimLogCommand extends Command
{
    public const int TRIM_ABOVE_BYTES = 20 * 1024 * 1024;

    public const int KEEP_BYTES = 10 * 1024 * 1024;

    /** A log entry starts with its timestamp, e.g. `[2026-11-14 10:00:00]`. */
    private const ENTRY_START = '/^\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/m';

    protected $signature = 'logs:trim';

    protected $description = 'Keep only the newest 10 MB of laravel.log once it grows past 20 MB';

    public function handle(): int
    {
        $path = (string) config('logging.channels.single.path');

        clearstatcache(true, $path);

        if (! is_file($path) || (int) filesize($path) <= self::TRIM_ABOVE_BYTES) {
            $this->info('laravel.log is within its size limit; nothing to trim.');

            return self::SUCCESS;
        }

        $handle = fopen($path, 'r+');

        if ($handle === false) {
            $this->error("Couldn't open {$path} to trim it.");

            return self::FAILURE;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                $this->error("Couldn't lock {$path} to trim it.");

                return self::FAILURE;
            }

            $sizeBefore = (int) fstat($handle)['size'];

            fseek($handle, max(0, $sizeBefore - self::KEEP_BYTES));
            $kept = $this->fromFirstWholeEntry((string) stream_get_contents($handle));

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $kept);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $message = sprintf('Trimmed laravel.log from %.1f MB to %.1f MB, keeping the newest entries.', $sizeBefore / 1048576, strlen($kept) / 1048576);

        Log::info($message);
        $this->info($message);

        return self::SUCCESS;
    }

    /**
     * Drops the partial entry the cut landed in, so the kept part starts at a
     * whole entry (or is empty when no entry starts within it).
     */
    private function fromFirstWholeEntry(string $tail): string
    {
        if (preg_match(self::ENTRY_START, $tail, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        return substr($tail, $match[0][1]);
    }
}
