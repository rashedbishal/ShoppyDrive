<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\MetaEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Superseded by MetaEventRecorder + SendMetaTrackingEventJob, which nothing
 * dispatches this any more. Kept only so a job of this class that was
 * already waiting in the production `jobs` table when that change deployed
 * still runs: it records the order's Purchase through the new outbox
 * (a no-op if the order already has one) instead of failing with a
 * missing class. Safe to delete once no such job can be left.
 */
class SendFacebookPurchaseEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(MetaEventRecorder $recorder): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        $recorder->recordPurchase($order, null);
    }
}
