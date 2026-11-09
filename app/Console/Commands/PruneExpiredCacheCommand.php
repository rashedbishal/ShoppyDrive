<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * DB-004 retest: split out of PruneOldDataCommand (data:prune) so expired
 * cache rows/files — mostly short-lived rate-limit counters, which can
 * expire within minutes — don't sit around for up to a day before the
 * next cleanup. The rest of data:prune's retention periods (incomplete
 * orders, guest carts, SMS) are properly day-granularity and stay on
 * their own nightly schedule; re-running that hourly too would add load
 * on shared hosting for no benefit.
 */
#[Signature('cache:prune-expired')]
#[Description('Delete expired rows from the database cache store and expired files from the file cache store.')]
class PruneExpiredCacheCommand extends Command
{
    /** Small batches, so a large first run doesn't hold locks on shared hosting. */
    private const BATCH_SIZE = 500;

    public function handle(): int
    {
        $deleted = [
            'expired_cache_rows' => $this->deleteExpiredCacheRows(),
            'expired_cache_files' => $this->deleteExpiredCacheFiles(),
        ];

        Log::info('[Retention] Expired cache pruned', $deleted);
        $this->info('Pruned: '.json_encode($deleted));

        return self::SUCCESS;
    }

    /**
     * DB-004: rate-limit counters live in the database cache, and a key that's
     * never read again is never removed.
     */
    private function deleteExpiredCacheRows(): int
    {
        $total = 0;

        do {
            $keys = DB::table('cache')->where('expiration', '<', now()->timestamp)->limit(self::BATCH_SIZE)->pluck('key');
            $deleted = $keys->isEmpty() ? 0 : DB::table('cache')->whereIn('key', $keys)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }

    /**
     * PERF-008: a file cache entry is only removed if read again after it
     * expires, which a flushed search suggestion never is. Each file starts
     * with its 10-digit expiry time.
     */
    private function deleteExpiredCacheFiles(): int
    {
        $directory = (string) config('cache.stores.file.path');

        if (! is_dir($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach (File::allFiles($directory) as $file) {
            $expiresAt = (string) @file_get_contents($file->getPathname(), length: 10);

            if (ctype_digit($expiresAt) && strlen($expiresAt) === 10 && (int) $expiresAt < now()->timestamp) {
                $deleted += (int) @unlink($file->getPathname());
            }
        }

        return $deleted;
    }
}
