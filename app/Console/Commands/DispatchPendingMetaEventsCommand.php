<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendMetaTrackingEventJob;
use App\Models\MetaTrackingEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The outbox sweeper for Meta Conversions API events. A row whose job was
 * lost (worker killed mid-send, queue table cleared) stays `pending` or
 * `sending`; once it has been untouched for longer than the job's longest
 * retry delay, it is queued again. The job's claim step makes a duplicate
 * job harmless, and the stored event id means Meta would deduplicate even a
 * repeated send. Meta refuses events older than 7 days, so anything still
 * undelivered after 6 days is marked failed instead.
 */
#[Signature('meta:dispatch-pending')]
#[Description('Re-queue Meta Conversions API events whose delivery job was lost.')]
class DispatchPendingMetaEventsCommand extends Command
{
    public const STALE_AFTER_MINUTES = 120;

    public const GIVE_UP_AFTER_DAYS = 6;

    public function handle(): int
    {
        $expired = MetaTrackingEvent::query()
            ->whereIn('status', [MetaTrackingEvent::STATUS_PENDING, MetaTrackingEvent::STATUS_SENDING])
            ->where('event_time', '<', now()->subDays(self::GIVE_UP_AFTER_DAYS))
            ->update([
                'status' => MetaTrackingEvent::STATUS_FAILED,
                'last_error' => 'Not delivered within '.self::GIVE_UP_AFTER_DAYS.' days; Meta no longer accepts it.',
                'updated_at' => now(),
            ]);

        $requeued = 0;

        MetaTrackingEvent::query()
            ->whereIn('status', [MetaTrackingEvent::STATUS_PENDING, MetaTrackingEvent::STATUS_SENDING])
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'status'])
            ->each(function (MetaTrackingEvent $event) use (&$requeued): void {
                MetaTrackingEvent::query()
                    ->whereKey($event->id)
                    ->where('status', $event->status)
                    ->update(['status' => MetaTrackingEvent::STATUS_PENDING, 'updated_at' => now()]);

                SendMetaTrackingEventJob::dispatch($event->id);
                $requeued++;
            });

        $this->info("Re-queued {$requeued}, gave up on {$expired}.");

        return self::SUCCESS;
    }
}
