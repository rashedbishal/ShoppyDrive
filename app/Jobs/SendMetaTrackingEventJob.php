<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\MetaConversionsApiException;
use App\Models\MetaTrackingEvent;
use App\Services\FacebookConversionsApiService;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one stored MetaTrackingEvent to Meta. The event id lives on the
 * row, so every retry sends exactly the same event id (and Meta would
 * deduplicate even a repeat that slipped through).
 *
 * The row is claimed (pending/failed -> sending) with a single conditional
 * update, so two workers, a sweeper re-dispatch or an admin retry can never
 * send it twice at once. Temporary failures (timeout, 429, 5xx, Meta's
 * `is_transient`) are retried with growing delays; a permanent rejection is
 * recorded and not retried. Either final failure lands in Failed Jobs.
 */
class SendMetaTrackingEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * Delays before each retry: 1 min, 5 min, 15 min, 30 min, 1 h.
     *
     * @var list<int>
     */
    public const RETRY_DELAYS = [60, 300, 900, 1800, 3600];

    public int $tries = 6;

    public function __construct(
        public readonly int $metaTrackingEventId,
    ) {}

    public function handle(FacebookConversionsApiService $service): void
    {
        $claimed = MetaTrackingEvent::query()
            ->whereKey($this->metaTrackingEventId)
            ->whereIn('status', [MetaTrackingEvent::STATUS_PENDING, MetaTrackingEvent::STATUS_FAILED])
            ->update([
                'status' => MetaTrackingEvent::STATUS_SENDING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return;
        }

        $event = MetaTrackingEvent::query()->find($this->metaTrackingEventId);

        // Cleared from Admin > Pixel & CAPI Log between the claim and here.
        if ($event === null) {
            return;
        }

        // Switched off in Admin after the event was recorded: nothing to
        // retry, and not an error worth a Failed Jobs entry.
        if (! FacebookConversionsApiConfig::isAvailable()) {
            $this->markFailed($event, 'The Conversions API was switched off or not configured when this event was due.', null);

            return;
        }

        try {
            $service->send($event);
        } catch (MetaConversionsApiException $e) {
            $this->handleFailure($event, $e);

            return;
        }

        $event->forceFill([
            'status' => MetaTrackingEvent::STATUS_SENT,
            'sent_at' => now(),
            'response_status' => 200,
            'last_error' => null,
        ])->save();
    }

    private function handleFailure(MetaTrackingEvent $event, MetaConversionsApiException $e): void
    {
        $canRetry = $e->retryable && $this->attempts() < $this->tries;

        if ($canRetry) {
            $event->forceFill([
                'status' => MetaTrackingEvent::STATUS_PENDING,
                'response_status' => $e->responseStatus,
                'last_error' => $e->getMessage(),
            ])->save();

            $this->release(self::RETRY_DELAYS[min($this->attempts(), count(self::RETRY_DELAYS)) - 1] ?? 3600);

            return;
        }

        $this->markFailed($event, $e->getMessage(), $e->responseStatus);

        Log::warning('[Meta CAPI] Event not delivered', [
            'meta_tracking_event_id' => $event->id,
            'event_name' => $event->event_name,
            'retryable' => $e->retryable,
            'error' => $e->getMessage(),
        ]);

        $this->fail($e);
    }

    private function markFailed(MetaTrackingEvent $event, string $error, ?int $responseStatus): void
    {
        $event->forceFill([
            'status' => MetaTrackingEvent::STATUS_FAILED,
            'response_status' => $responseStatus,
            'last_error' => $error,
        ])->save();
    }
}
