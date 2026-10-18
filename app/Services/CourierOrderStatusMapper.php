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

    /** @var array<string, OrderStatus> */
    private const STEADFAST_MAP = [
        'pending' => OrderStatus::PENDING,
        'in_review' => OrderStatus::PENDING,

        'hold' => OrderStatus::PROCESSING,
        'unknown_approval_pending' => OrderStatus::PROCESSING,
        'exceptional' => OrderStatus::PROCESSING,

        'delivered_approval_pending' => OrderStatus::SHIPPED,
        'partial_delivered_approval_pending' => OrderStatus::SHIPPED,
        'cancelled_approval_pending' => OrderStatus::SHIPPED,

        'delivered' => OrderStatus::DELIVERED,
        'partial_delivered' => OrderStatus::DELIVERED,

        'cancelled' => OrderStatus::CANCELLED,
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
        return ucwords(str_replace('_', ' ', self::normalize($rawStatus)));
    }

    /**
     * Record a provider's raw status on the order and, when it maps to a
     * different lifecycle status, advance the order's status to match.
     * A refunded order is a terminal payment decision and is never moved by
     * a courier status. No-ops (and writes nothing) when the raw status is
     * unchanged from what's already stored, so repeated polls/webhook
     * retries don't create duplicate history entries.
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
            && $order->status !== OrderStatus::REFUNDED;

        $updates = [$statusField => $rawStatus];

        if ($statusChanged) {
            $updates['status'] = $mapped;

            if ($mapped === OrderStatus::DELIVERED) {
                $updates['delivered_at'] = now();
                $updates['payment_status'] = PaymentStatus::PAID;
            }
        }

        $order->update($updates);

        $note = ucfirst($provider).' reported "'.self::label($rawStatus).'"'
            .($statusChanged ? ' — order status updated to '.$mapped->value.'.' : '.');

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
