<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\FacebookConversionsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched from Order::booted()'s single `created` choke point (fires for
 * every order-creation path), after the enclosing transaction commits.
 * Queued rather than sent inline because this is a third-party HTTP call —
 * exactly the kind of transient-failure-prone operation `$tries`/`$backoff`
 * exist for, same as SendNewsletterChunk.
 */
class SendFacebookPurchaseEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(FacebookConversionsApiService $service): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        $service->sendPurchaseEvent($order);
    }
}
