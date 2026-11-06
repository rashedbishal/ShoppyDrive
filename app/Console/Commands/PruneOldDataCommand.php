<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\IncompleteOrder;
use App\Models\ReceivedSms;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

        Log::info('[Retention] Old data pruned', $deleted);
        $this->info('Pruned: '.json_encode($deleted));

        return self::SUCCESS;
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
