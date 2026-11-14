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
        // DISC-20: Pathao's own spellings, from its official WooCommerce
        // plugin's status vocabulary (`Pickup_Requested`, `Picked`,
        // `Delivered`, …), which production's `/info` reports also use. The
        // older keys stay alongside. `Delivery_Failed` and `Pickup_Failed`
        // are deliberately unmapped and flagged (need a human); `Return` and
        // `Payment_Invoice` are preserved (see PATHAO_PRESERVED).
        'pickup_requested' => OrderStatus::PENDING,
        'assigned_for_pickup' => OrderStatus::PENDING,

        'picked' => OrderStatus::PROCESSING,
        'at_the_sorting_hub' => OrderStatus::PROCESSING,
        'at_sorting_hub' => OrderStatus::PROCESSING,
        'at_sorting' => OrderStatus::PROCESSING,
        'first_mile_hub' => OrderStatus::PROCESSING,

        'assigned_for_delivery' => OrderStatus::SHIPPED,
        'received_at_last_mile_hub' => OrderStatus::SHIPPED,
        'at_delivery_hub' => OrderStatus::SHIPPED,
        'in_transit' => OrderStatus::SHIPPED,
        'reattempt_requested' => OrderStatus::SHIPPED,

        'delivered' => OrderStatus::DELIVERED,
        'delivery' => OrderStatus::DELIVERED,
        // DISC-03 (owner decision): one order is one delivery unit, so
        // `partial_delivery` is deliberately unmapped and never becomes
        // DELIVERED. It is still recorded as evidence (is_partial) and
        // flagged on the order; the order keeps its current status.

        // DISC-20: the status Pathao reports after a merchant cancels the
        // parcel in the Pathao dashboard (owner-confirmed, order 9). Every
        // return status is deliberately NOT here: see PATHAO_PRESERVED.
        'pickup_cancelled' => OrderStatus::CANCELLED,
    ];

    /**
     * DISC-20 (owner decisions 2026-11-13/14): known Pathao statuses that
     * deliberately leave the order's own status unchanged. The raw status is
     * still stored and shown to the admin as the courier status, with its
     * evidence row, but these are routine, so they are not flagged as
     * "unrecognized" (that banner stays for lost, damaged, partial delivery,
     * failed attempts and any key never seen before). Return statuses stay
     * here until Pathao confirms which one means the parcel is back.
     *
     * @var list<string>
     */
    private const PATHAO_PRESERVED = [
        'on_hold',
        'return_on_hold',
        'exchange',
        'reprocess_pending',
        'reprocessed',
        'at_inventory',
        'return',
        'return_requested',
        'return_in_transit',
        'return_at_sorting',
        'assigned_for_return',
        'returned',
        'paid_return',
        'returned_to_inventory',
        'return_to_merchant',
        'return_to_inventory',
        'payment_invoice',
    ];

    /**
     * BIZ-027: from Steadfast's own official API docs' "Status reference"
     * section (portal.packzy.com/api/v1), not a third-party SDK's guess.
     * `hold`, every `*_approval_pending` value, `exceptional` and `unknown`
     * are deliberately left out — the docs say outright "do not settle your
     * books on it [an `_approval_pending` value] — wait for delivered,
     * partial_delivered or cancelled", and `hold`/`exceptional` need a
     * human ("contact support"), not an automatic status change. Any status
     * missing from this map is still recorded on the order and flagged as
     * unrecognized (see isUnrecognized()) — the order's own status is never
     * touched for it.
     *
     * @var array<string, OrderStatus>
     */
    private const STEADFAST_MAP = [
        'pending' => OrderStatus::PENDING,
        'in_review' => OrderStatus::PENDING,
        // DISC-29: the six statuses Steadfast told the owner it provides,
        // spelling confirmed exact by Steadfast (2026-11-14). The API docs'
        // spellings stay alongside (owner decision) as a harmless fallback.
        'pending_parcels' => OrderStatus::PENDING,

        'delivered' => OrderStatus::DELIVERED,
        'delivered_parcels' => OrderStatus::DELIVERED,
        // DISC-03: `partial_delivered` deliberately unmapped (see PATHAO_MAP).

        'cancelled' => OrderStatus::CANCELLED,
        'cancelled_parcels' => OrderStatus::CANCELLED,
    ];

    /**
     * DISC-29 (owner decision 2026-11-14): known Steadfast statuses that
     * leave the order's own status unchanged without the "unrecognized"
     * banner, like PATHAO_PRESERVED. `waiting_approval` is a routine step
     * before Steadfast settles a parcel as delivered or cancelled.
     *
     * @var list<string>
     */
    private const STEADFAST_PRESERVED = [
        'waiting_approval',
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
     * FSA-11: whether a provider's raw status falls outside this app's
     * known vocabulary for it. `sync()` already handles this safely on its
     * own — the raw status is still recorded as evidence/history, but the
     * order's own status is never changed for anything unrecognized — this
     * is purely a signal surfaced to admins (e.g. on the order page) that a
     * report worth investigating manually came in, such as a lost parcel.
     * Neither courier's confirmed status vocabulary (see PATHAO_MAP/
     * STEADFAST_MAP) includes a distinct "lost" value today, so this is
     * the closest safe proxy without inventing one.
     */
    public static function isUnrecognized(string $provider, string $rawStatus): bool
    {
        if ($provider === 'pathao') {
            return self::forPathao($rawStatus) === null
                && ! in_array(self::normalize($rawStatus), self::PATHAO_PRESERVED, true);
        }

        return self::forSteadfast($rawStatus) === null
            && ! in_array(self::normalize($rawStatus), self::STEADFAST_PRESERVED, true);
    }

    /**
     * FSA-11: whether $status is one of this mapper's own terminal
     * outcomes (see TERMINAL_STATUSES) — exposed so callers outside this
     * class (e.g. the admin order page) can reuse the same single
     * definition instead of duplicating the list.
     */
    public static function isTerminal(OrderStatus $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
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
     * MED-02: the active statuses in lifecycle order. Steadfast pushes
     * webhooks that can arrive late, and every status it reports moves
     * forward, so a report mapping to an earlier status here is stale. It
     * must not act: returning a shipped order to pending would let the
     * customer cancel it, restoring stock while the parcel is moving.
     *
     * Pathao is deliberately excluded. It is polled, so each report is its
     * current state rather than a delayed event, and it can genuinely step
     * back (a shipped parcel going `on_hold`).
     *
     * @var list<OrderStatus>
     */
    private const FORWARD_ONLY_STATUSES = [OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::SHIPPED];

    /**
     * Record a provider's raw status on the order and, when it maps to a
     * different lifecycle status, advance the order's status to match —
     * unless the order is already in a terminal status (see
     * TERMINAL_STATUSES) or the report is stale (see FORWARD_ONLY_STATUSES),
     * in which case only the raw tracking field is recorded (so admins can
     * still see what the courier reported) and a history note is written,
     * but the order's own status/payment_status is never touched. No-ops (and writes nothing at all) when the raw
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
                && ! in_array($lockedOrder->status, self::TERMINAL_STATUSES, true)
                && ! ($provider === 'steadfast' && self::movesBackward($lockedOrder->status, $mapped));

            $updates = [$statusField => $rawStatus];

            if ($statusChanged) {
                $updates['status'] = $mapped;

                if ($mapped === OrderStatus::DELIVERED) {
                    $updates['delivered_at'] = now();
                    $updates['payment_status'] = PaymentStatus::PAID;
                }
            }

            // DISC-02 (owner decision): once a parcel is with a courier, no
            // courier report may put stock back. Saved with the status
            // change, so the CANCELLED hook's InventoryService::restoreForOrder()
            // already sees it and skips (the BIZ-012 flag); the admin restocks
            // by hand in Products when the goods are physically back.
            $restockSkipped = $statusChanged && $mapped === OrderStatus::CANCELLED;

            if ($restockSkipped && $lockedOrder->restock_skipped_at === null) {
                $lockedOrder->forceFill(['restock_skipped_at' => now()]);
            }

            $lockedOrder->update($updates);

            $ignored = ! $statusChanged
                && $mapped !== null
                && $mapped !== $lockedOrder->status;

            $note = ucfirst($provider).' reported "'.self::label($rawStatus).'"'.match (true) {
                $statusChanged => ' — order status updated to '.$mapped->value.'.',
                $ignored => " — order is already {$lockedOrder->status->value}; status left unchanged.",
                default => '.',
            };

            if ($restockSkipped) {
                $note .= ' Stock was not restored automatically; restock the items in Products if they are physically back.';
            }

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

    private static function movesBackward(OrderStatus $current, OrderStatus $reported): bool
    {
        $currentPosition = array_search($current, self::FORWARD_ONLY_STATUSES, true);
        $reportedPosition = array_search($reported, self::FORWARD_ONLY_STATUSES, true);

        return $currentPosition !== false && $reportedPosition !== false && $reportedPosition < $currentPosition;
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
