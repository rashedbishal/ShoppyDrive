<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

class CourierOrderStatusMapper
{
    /** @var array<string, OrderStatus> */
    private const PATHAO_MAP = [
        'pending' => OrderStatus::PENDING,

        'at_sorting_hub' => OrderStatus::PROCESSING,
        'at_sorting' => OrderStatus::PROCESSING,
        'first_mile_hub' => OrderStatus::PROCESSING,
        'reprocess_pending' => OrderStatus::PROCESSING,
        'reprocessed' => OrderStatus::PROCESSING,
        'at_inventory' => OrderStatus::PROCESSING,
        'on_hold' => OrderStatus::PROCESSING,
        'exchange' => OrderStatus::PROCESSING,

        'assigned_for_delivery' => OrderStatus::SHIPPED,
        'at_delivery_hub' => OrderStatus::SHIPPED,
        'in_transit' => OrderStatus::SHIPPED,
        'reattempt_requested' => OrderStatus::SHIPPED,

        'delivery' => OrderStatus::DELIVERED,
        'partial_delivery' => OrderStatus::DELIVERED,

        'pickup_cancelled' => OrderStatus::CANCELLED,
        'return_requested' => OrderStatus::CANCELLED,
        'return_at_sorting' => OrderStatus::CANCELLED,
        'return_in_transit' => OrderStatus::CANCELLED,
        'assigned_for_return' => OrderStatus::CANCELLED,
        'return_to_merchant' => OrderStatus::CANCELLED,
        'returned' => OrderStatus::CANCELLED,
        'paid_return' => OrderStatus::CANCELLED,
        'returned_to_inventory' => OrderStatus::CANCELLED,
        'return_to_inventory' => OrderStatus::CANCELLED,
    ];

    /**
     * The complete set of statuses Steadfast reports, as confirmed directly
     * by Steadfast — not the broader generic set in their public docs.
     *
     * @var array<string, OrderStatus>
     */
    private const STEADFAST_MAP = [
        'in_review' => OrderStatus::PENDING,
        'pending_parcels' => OrderStatus::PENDING,

        'waiting_approval' => OrderStatus::PROCESSING,

        'delivered_parcels' => OrderStatus::DELIVERED,
        'partial_parcels' => OrderStatus::DELIVERED,

        'cancelled_parcells' => OrderStatus::CANCELLED,
    ];

    public static function forPathao(string $rawStatus): ?OrderStatus
    {
        return self::PATHAO_MAP[self::normalize($rawStatus)] ?? null;
    }

    public static function forSteadfast(string $rawStatus): ?OrderStatus
    {
        return self::STEADFAST_MAP[self::normalize($rawStatus)] ?? null;
    }

    /**
     * Turn a raw courier status (e.g. "assigned_for_delivery") into
     * human-readable text (e.g. "Assigned For Delivery").
     */
    public static function label(string $rawStatus): string
    {
        $label = ucwords(str_replace('_', ' ', self::normalize($rawStatus)));

        // Steadfast's own "cancelled_parcells" value has a typo; correct it for display only.
        return str_replace('Parcells', 'Parcels', $label);
    }

    /**
     * Order statuses a courier-reported status is never allowed to move
     * the order away from. Cancelled and refunded are both terminal
     * outcomes already decided by the store (an admin cancellation, a
     * refund) — a late or retried courier webhook (e.g. a "delivered"
     * notification that arrives after the order was cancelled, or a
     * duplicate/out-of-order delivery report) must never resurrect the
     * order into an active status or mark it paid again.
     *
     * @var list<OrderStatus>
     */
    private const TERMINAL_STATUSES = [OrderStatus::CANCELLED, OrderStatus::REFUNDED];

    /**
     * Record a provider's raw status on the order and, when it maps to a
     * different lifecycle status, advance the order's status to match —
     * unless the order is already in a terminal status (see
     * TERMINAL_STATUSES), in which case only the raw tracking field is
     * recorded (so admins can still see what the courier reported) and a
     * history note is written, but the order's own status/payment_status
     * is never touched. No-ops (and writes nothing at all) when the raw
     * status is unchanged from what's already stored, so repeated
     * polls/webhook retries don't create duplicate history entries.
     */
    public static function sync(Order $order, string $provider, string $rawStatus, ?string $extraNote = null): void
    {
        $statusField = $provider === 'pathao' ? 'pathao_order_status' : 'steadfast_status';

        if ($order->{$statusField} === $rawStatus) {
            return;
        }

        $mapped = $provider === 'pathao' ? self::forPathao($rawStatus) : self::forSteadfast($rawStatus);
        $statusChanged = $mapped !== null
            && $mapped !== $order->status
            && ! in_array($order->status, self::TERMINAL_STATUSES, true);

        $updates = [$statusField => $rawStatus];

        if ($statusChanged) {
            $updates['status'] = $mapped;

            if ($mapped === OrderStatus::DELIVERED) {
                $updates['delivered_at'] = now();
                $updates['payment_status'] = PaymentStatus::PAID;
            }
        }

        $order->update($updates);

        $ignoredAsTerminal = ! $statusChanged
            && $mapped !== null
            && $mapped !== $order->status
            && in_array($order->status, self::TERMINAL_STATUSES, true);

        $note = ucfirst($provider).' reported "'.self::label($rawStatus).'"'.match (true) {
            $statusChanged => ' — order status updated to '.$mapped->value.'.',
            $ignoredAsTerminal => " — order is already {$order->status->value}; status left unchanged.",
            default => '.',
        };

        if ($extraNote) {
            $note .= ' '.$extraNote;
        }

        $order->statusHistories()->create([
            'status' => $order->status->value,
            'notes' => $note,
        ]);
    }

    private static function normalize(string $rawStatus): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim($rawStatus)));
    }
}
