<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Models\Cart;
use App\Models\IncompleteOrder;
use App\Models\ReceivedSms;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * DB-002: retention periods (owner-approved) for tables that otherwise grow
 * forever and hold personal data. Orders, payments, refunds, pending
 * checkouts and accounts are never touched here. Failed jobs, job batches
 * and the audit trail are cleaned by their own scheduled commands; see
 * routes/console.php.
 *
 * SMS age is measured by created_at (our server's receive time), not the
 * forwarder phone's received_at. Matched SMS are kept longer as payment
 * evidence; order deletion is still blocked by the Payment record itself.
 */
#[Signature('data:prune')]
#[Description('Delete unfinished checkouts, guest carts and received SMS past their retention period.')]
class PruneOldDataCommand extends Command
{
    public const INCOMPLETE_ORDER_DAYS = 60;

    public const GUEST_CART_DAYS = 30;

    public const UNMATCHED_SMS_DAYS = 90;

    public const MATCHED_SMS_DAYS = 365;

    /** Small batches, so a large first run doesn't hold locks on shared hosting. */
    private const BATCH_SIZE = 500;

    public function handle(): int
    {
        $deleted = [
            'incomplete_orders' => $this->deleteInBatches(IncompleteOrder::query()->where('updated_at', '<', $this->daysAgo(self::INCOMPLETE_ORDER_DAYS))),
            'guest_carts' => $this->deleteInBatches(Cart::query()->whereNull('user_id')->where('updated_at', '<', $this->daysAgo(self::GUEST_CART_DAYS))),
            'unmatched_sms' => $this->deleteInBatches(ReceivedSms::query()->where('is_matched', false)->where('created_at', '<', $this->daysAgo(self::UNMATCHED_SMS_DAYS))),
            'matched_sms' => $this->deleteInBatches(ReceivedSms::query()->where('is_matched', true)->where('created_at', '<', $this->daysAgo(self::MATCHED_SMS_DAYS))),
        ];

        $deleted['abandoned_restore_uploads'] = $this->deleteAbandonedRestoreUploads();
        $deleted['expired_cache_rows'] = $this->deleteExpiredCacheRows();
        $deleted['expired_cache_files'] = $this->deleteExpiredCacheFiles();

        Log::info('[Retention] Old data pruned', $deleted);
        $this->info('Pruned: '.json_encode($deleted));

        return self::SUCCESS;
    }

    /**
     * OPS-008: an upload nobody confirmed is a full copy of the shop's data.
     */
    private function deleteAbandonedRestoreUploads(): int
    {
        $disk = Storage::disk('local');
        $abandoned = array_filter(
            $disk->files(DatabaseBackupController::UPLOAD_DIRECTORY),
            fn (string $path): bool => $disk->lastModified($path) < now()->subDay()->timestamp,
        );

        $disk->delete($abandoned);

        return count($abandoned);
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

    private function daysAgo(int $days): CarbonInterface
    {
        return now()->subDays($days);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function deleteInBatches(Builder $query): int
    {
        $total = 0;

        do {
            $ids = (clone $query)->limit(self::BATCH_SIZE)->pluck('id');
            $deleted = $ids->isEmpty() ? 0 : $query->getModel()->newQuery()->whereKey($ids)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
