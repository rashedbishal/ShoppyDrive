<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourierEvidenceSource;
use App\Enums\CourierEvidenceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CourierDeliveryEvidence;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

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
     * the order away from. Cancelled and refunded are terminal outcomes
     * already decided by the store (an admin cancellation, a refund) — a
     * late or retried courier webhook (e.g. a "delivered" notification
     * that arrives after the order was cancelled, or a duplicate/out-of-
     * order delivery report) must never resurrect the order into an
     * active status or mark it paid again.
     *
     * Delivered is terminal in the other direction: once a courier has
     * reported successful delivery, a later report (a correction, a
     * return initiated with the courier, an out-of-order retry) must not
     * regress the order back to pending/processing/cancelled either —
     * that would re-trigger Order::booted()'s stock-restore/coupon-
     * release/loyalty-reversal side effects for a sale that was actually
     * fulfilled. A delivered order that genuinely needs to be reversed
     * goes through the deliberate, admin-approved ReturnRequest workflow,
     * not an automatic courier status sync.
     *
     * @var list<OrderStatus>
     */
    private const TERMINAL_STATUSES = [OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::DELIVERED];

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
    public static function sync(Order $order, string $provider, string $rawStatus, ?string $extraNote = null, CourierEvidenceSource $source = CourierEvidenceSource::UNSPECIFIED): void
    {
        // The raw-status write, the status change, its history row and every
        // financial side effect Order::booted() runs for a cancellation
        // (redeemed-points refund, referral reversal, stock restore, coupon
        // release) commit or roll back together, and everything below reads
        // the locked row rather than the caller's possibly-stale copy — so a
        // webhook retry racing a scheduled poll or an admin action can't
        // record the same report twice. withTrashed() keeps the pre-existing
        // behavior of recording reports for a trashed order too.
        $found = DB::transaction(function () use ($order, $provider, $rawStatus, $extraNote, $source): bool {
            $lockedOrder = Order::withTrashed()->whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null) {
                return false;
            }

            $statusField = $provider === 'pathao' ? 'pathao_order_status' : 'steadfast_status';
            $consignmentId = $lockedOrder->{"{$provider}_consignment_id"};

            // PAY-RETURN-001: before anything else (even an unchanged report),
            // record any break in this consignment's evidence chain.
            self::recordEvidenceGaps($lockedOrder, $provider, $consignmentId, $statusField);

            if ($lockedOrder->{$statusField} === $rawStatus) {
                return true;
            }

            $previousRaw = $lockedOrder->{$statusField};
            $statusBefore = $lockedOrder->status;

            $mapped = $provider === 'pathao' ? self::forPathao($rawStatus) : self::forSteadfast($rawStatus);
            $statusChanged = $mapped !== null
                && $mapped !== $lockedOrder->status
                && ! in_array($lockedOrder->status, self::TERMINAL_STATUSES, true);

            $updates = [$statusField => $rawStatus];

            if ($statusChanged) {
                $updates['status'] = $mapped;

                if ($mapped === OrderStatus::DELIVERED) {
                    $updates['delivered_at'] = now();
                    $updates['payment_status'] = PaymentStatus::PAID;
                }
            }

            $lockedOrder->update($updates);

            $ignoredAsTerminal = ! $statusChanged
                && $mapped !== null
                && $mapped !== $lockedOrder->status
                && in_array($lockedOrder->status, self::TERMINAL_STATUSES, true);

            $note = ucfirst($provider).' reported "'.self::label($rawStatus).'"'.match (true) {
                $statusChanged => ' — order status updated to '.$mapped->value.'.',
                $ignoredAsTerminal => " — order is already {$lockedOrder->status->value}; status left unchanged.",
                default => '.',
            };

            if ($extraNote) {
                $note .= ' '.$extraNote;
            }

            $history = $lockedOrder->statusHistories()->create([
                'status' => $lockedOrder->status->value,
                'notes' => $note,
            ]);

            // PAY-RETURN-001: the structured evidence row, linked to its
            // display note, in the same transaction as the status change.
            $normalized = self::normalize($rawStatus);
            $isPartial = str_contains($normalized, 'partial');

            CourierDeliveryEvidence::create([
                'order_id' => $lockedOrder->id,
                'provider' => $provider,
                'consignment_id' => $consignmentId,
                'order_status_history_id' => $history->id,
                'evidence_type' => $statusChanged ? CourierEvidenceType::TRANSITION : CourierEvidenceType::OBSERVATION,
                'source' => $source,
                'code_generation' => CourierDeliveryEvidence::CODE_GENERATION,
                'previous_raw_status' => $previousRaw,
                'raw_status' => $rawStatus,
                'normalized_status' => $normalized,
                'mapped_order_status' => $mapped?->value,
                'order_status_before' => $statusBefore->value,
                'order_status_after' => $lockedOrder->status->value,
                'is_partial' => $isPartial,
                'is_full_delivery' => $mapped === OrderStatus::DELIVERED && ! $isPartial,
                'received_at' => now(),
            ]);

            return true;
        }, 3);

        // Callers keep using the instance they passed in, so bring it up to
        // date with what was just committed.
        if ($found) {
            $order->refresh();
        }
    }

    /**
     * PAY-RETURN-001 gap detection (call with the order row locked):
     *  - the stored raw status differs from the last evidence row's — a raw
     *    change happened without evidence;
     *  - a courier-format history note after the consignment's anchor has no
     *    evidence row — something wrote a courier report without evidence.
     * Each gap is recorded once (gap_key) and permanently breaks the chain.
     */
    private static function recordEvidenceGaps(Order $order, string $provider, ?string $consignmentId, string $statusField): void
    {
        $chain = CourierDeliveryEvidence::where('order_id', $order->id)
            ->where('provider', $provider)
            ->where('consignment_id', $consignmentId)
            ->whereIn('evidence_type', [CourierEvidenceType::ANCHOR, CourierEvidenceType::TRANSITION, CourierEvidenceType::OBSERVATION, CourierEvidenceType::GAP]);

        $last = (clone $chain)->latest('id')->first();
        $stored = $order->{$statusField};

        if ($last !== null && $last->raw_status !== $stored) {
            $key = "mismatch:{$last->id}:".sha1((string) $stored);

            if (! CourierDeliveryEvidence::where('gap_key', $key)->exists()) {
                self::writeGap($order, $provider, $consignmentId, 'raw_mismatch', $key, $last->raw_status, $stored, null);
            }
        }

        $anchor = (clone $chain)->where('evidence_type', CourierEvidenceType::ANCHOR)->oldest('id')->first();

        if ($anchor === null) {
            return;
        }

        $unlinked = OrderStatusHistory::where('order_id', $order->id)
            ->where('id', '>', (int) $anchor->history_watermark_id)
            ->where(fn ($q) => $provider === 'pathao'
                ? $q->where('notes', 'like', 'Pathao reported "%')
                : $q->where('notes', 'like', 'Steadfast reported "%')->orWhere('notes', 'like', 'Steadfast status updated to "%'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('courier_delivery_evidence')->whereColumn('courier_delivery_evidence.order_status_history_id', 'order_status_histories.id'))
            ->orderBy('id')
            ->get(['id']);

        foreach ($unlinked as $history) {
            self::writeGap($order, $provider, $consignmentId, 'unlinked_history', "unlinked:{$history->id}", $stored, $stored, $history->id);
        }
    }

    private static function writeGap(Order $order, string $provider, ?string $consignmentId, string $reason, string $key, ?string $previousRaw, ?string $raw, ?int $historyId): void
    {
        CourierDeliveryEvidence::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'consignment_id' => $consignmentId,
            'order_status_history_id' => $historyId,
            'evidence_type' => CourierEvidenceType::GAP,
            'source' => CourierEvidenceSource::UNSPECIFIED,
            'code_generation' => CourierDeliveryEvidence::CODE_GENERATION,
            'previous_raw_status' => $previousRaw,
            'raw_status' => $raw,
            'normalized_status' => $raw === null ? null : self::normalize($raw),
            'is_partial' => $raw !== null && str_contains(self::normalize($raw), 'partial'),
            'gap_reason' => $reason,
            'gap_key' => $key,
            'received_at' => now(),
        ]);
    }

    private static function normalize(string $rawStatus): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim($rawStatus)));
    }
}
