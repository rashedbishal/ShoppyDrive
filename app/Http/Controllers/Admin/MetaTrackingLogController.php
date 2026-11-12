<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\PruneOldDataCommand;
use App\Http\Controllers\Controller;
use App\Models\MetaTrackingEvent;
use App\Support\AdminListUrl;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Pixel & CAPI Log: a read-only view of the meta_tracking_events
 * outbox (one row per Conversions API event, see MetaEventRecorder) and its
 * delivery outcome, plus a way to empty it. Browser Pixel fires go straight
 * from GTM to Meta and are never seen by the server, so the status shown is
 * the server-side delivery only; each row shares its event id with the
 * matching Pixel event.
 *
 * Clearing deletes every row up to the newest one present when the request
 * started, including events not yet delivered (the owner chose "everything"),
 * in batches so a large table never becomes one long-running DELETE. A row
 * deleted while its delivery job is queued is harmless: the job's claim
 * step finds nothing and stops.
 */
class MetaTrackingLogController extends Controller
{
    private const PER_PAGE = 25;

    private const DELETE_BATCH_SIZE = 1000;

    public function index(Request $request): Response|RedirectResponse
    {
        $events = MetaTrackingEvent::query()
            ->with(['order:id,order_number', 'user:id,name'])
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (MetaTrackingEvent $event): array => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_id' => $event->event_id,
                'status' => $event->status,
                'attempts' => $event->attempts,
                'response_status' => $event->response_status,
                'last_error' => $event->last_error,
                'action_source' => $event->action_source,
                'event_source_url' => $event->event_source_url,
                'custom_data' => $event->custom_data,
                'client_ip_address' => $event->client_ip_address,
                'client_user_agent' => $event->client_user_agent,
                'fbp' => $event->fbp,
                'fbc' => $event->fbc,
                'order' => $event->order !== null ? ['id' => $event->order->id, 'order_number' => $event->order->order_number] : null,
                'customer' => $event->user !== null ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
                'event_time' => $event->event_time,
                'sent_at' => $event->sent_at,
                'created_at' => $event->created_at,
            ]);

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($events, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        return Inertia::render('admin/pixel-capi-log/index', [
            'events' => $events,
            'statusCounts' => $this->statusCounts(),
            'conversionsApiAvailable' => FacebookConversionsApiConfig::isAvailable(),
            'retentionDays' => PruneOldDataCommand::META_TRACKING_EVENT_DAYS,
            'canDelete' => Auth::guard('admin')->user()->canAccess('pixel_capi_log', 'delete'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        $newestId = MetaTrackingEvent::query()->max('id');

        if ($newestId === null) {
            return back()->with('error', 'The log is already empty.');
        }

        $deleted = 0;

        do {
            $ids = MetaTrackingEvent::query()
                ->where('id', '<=', $newestId)
                ->orderBy('id')
                ->limit(self::DELETE_BATCH_SIZE)
                ->pluck('id');

            $batch = $ids->isEmpty() ? 0 : MetaTrackingEvent::query()->whereKey($ids)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        activity('admin')
            ->causedBy(Auth::guard('admin')->user())
            ->event('cleared')
            ->withProperties(['deleted' => $deleted])
            ->log("Pixel & CAPI log cleared ({$deleted} events deleted).");

        return redirect()->route('admin.pixel-capi-log.index')->with('success', "Deleted {$deleted} logged ".($deleted === 1 ? 'event' : 'events').'.');
    }

    /**
     * @return array{pending: int, sending: int, sent: int, failed: int}
     */
    private function statusCounts(): array
    {
        $counts = MetaTrackingEvent::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts[MetaTrackingEvent::STATUS_PENDING] ?? 0),
            'sending' => (int) ($counts[MetaTrackingEvent::STATUS_SENDING] ?? 0),
            'sent' => (int) ($counts[MetaTrackingEvent::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[MetaTrackingEvent::STATUS_FAILED] ?? 0),
        ];
    }
}
