<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendMetaTrackingEventJob;
use App\Models\MetaTrackingEvent;
use App\Models\Order;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * The only place MetaTrackingEvent rows are created (the outbox for the
 * Conversions API). Each row is keyed by (event_name, event_id); recording
 * the same pair twice is a no-op, so a double-clicked button, a retried
 * browser request or a repeated order hook never becomes a second event.
 *
 * Purchase is recorded only from a real order, inside the order's own
 * database transaction — if the order rolls back, so does its Purchase.
 * Its job is queued after commit, and `meta:dispatch-pending` re-queues any
 * row whose job was lost, so delivery never depends on the customer's
 * browser staying open.
 */
class MetaEventRecorder
{
    /**
     * Deterministic and stored with the order, so the confirmation page,
     * the server and every retry use exactly the same id.
     */
    public static function purchaseEventId(Order $order): string
    {
        return 'order-'.$order->order_number;
    }

    public function recordPurchase(Order $order, ?Request $request): ?MetaTrackingEvent
    {
        if (! FacebookConversionsApiConfig::isAvailable()) {
            return null;
        }

        // An order a staff member creates in Admin (manual order, converted
        // incomplete order) is a phone/staff sale: still a purchase, but the
        // request's IP, browser and Facebook cookies are the staff member's,
        // not the customer's, so none of them are sent.
        $isStaffOrder = $request !== null && $request->is('admin', 'admin/*');

        return $this->record([
            'event_name' => 'Purchase',
            'event_id' => self::purchaseEventId($order),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'action_source' => $isStaffOrder ? 'phone_call' : 'website',
            'event_source_url' => $isStaffOrder ? null : route('order.confirmation', $order->order_number),
            // Built from the order's items when sent (they don't exist yet
            // while the order row is being created).
            'custom_data' => [],
            'client_ip_address' => $isStaffOrder ? null : $order->ip_address,
            'client_user_agent' => $isStaffOrder ? null : $order->user_agent,
            'fbp' => $isStaffOrder ? null : $this->fbp($request),
            'fbc' => $isStaffOrder ? null : $this->fbc($request, null),
            'event_time' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $customData  already built from the database, never from browser-supplied prices
     */
    public function recordBrowserEvent(string $eventName, string $eventId, array $customData, string $eventSourceUrl, Request $request): ?MetaTrackingEvent
    {
        if (! FacebookConversionsApiConfig::isAvailable()) {
            return null;
        }

        return $this->record([
            'event_name' => $eventName,
            'event_id' => $eventId,
            'user_id' => $request->user()?->id,
            'action_source' => 'website',
            'event_source_url' => $eventSourceUrl,
            'custom_data' => $customData,
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => $this->fbp($request),
            'fbc' => $this->fbc($request, $eventSourceUrl),
            'event_time' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): ?MetaTrackingEvent
    {
        $alreadyRecorded = MetaTrackingEvent::query()
            ->where('event_name', $attributes['event_name'])
            ->where('event_id', $attributes['event_id'])
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        try {
            $event = MetaTrackingEvent::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // A concurrent duplicate won the race; it is the one event.
            return null;
        }

        SendMetaTrackingEventJob::dispatch($event->id)->afterCommit();

        return $event;
    }

    /**
     * The Meta Pixel's own first-party browser id cookie, e.g. fb.1.1596403881668.1116446470.
     */
    private function fbp(?Request $request): ?string
    {
        $fbp = (string) $request?->cookie('_fbp');

        return preg_match('/^fb\.\d\.\d{10,13}\.\d{1,20}$/', $fbp) === 1 ? $fbp : null;
    }

    /**
     * The Pixel's click-id cookie, or — when the visitor has just arrived
     * from an ad and the Pixel hasn't written it yet — built from the
     * fbclid in the page URL, in Meta's documented fb.1.<ms>.<fbclid> form.
     */
    private function fbc(?Request $request, ?string $eventSourceUrl): ?string
    {
        $fbc = (string) $request?->cookie('_fbc');

        if (preg_match('/^fb\.\d\.\d{10,13}\.[A-Za-z0-9_-]{1,200}$/', $fbc) === 1) {
            return $fbc;
        }

        parse_str((string) parse_url((string) $eventSourceUrl, PHP_URL_QUERY), $query);
        $fbclid = is_string($query['fbclid'] ?? null) ? $query['fbclid'] : '';

        return preg_match('/^[A-Za-z0-9_-]{1,200}$/', $fbclid) === 1
            ? 'fb.1.'.now()->getTimestampMs().'.'.$fbclid
            : null;
    }
}
