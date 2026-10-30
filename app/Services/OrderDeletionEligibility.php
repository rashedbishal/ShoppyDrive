<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CouponUse;
use App\Models\CourierSubmission;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReceivedSms;
use App\Models\ReferralPointTransaction;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * DB-001 (L1): the single decision on whether an order may be permanently
 * deleted. Only a soft-deleted, cancelled order with no financial, loyalty,
 * coupon, fulfilment, courier, payment-evidence, inventory or review history
 * qualifies. Database foreign keys (protect_order_history_foreign_keys)
 * independently refuse to delete the financial rows, whatever the caller.
 */
class OrderDeletionEligibility
{
    /**
     * Locks the order row in its own transaction and returns the reasons it
     * can't be permanently deleted (empty = eligible). For display and early
     * refusal; the deletion itself re-checks under its own lock.
     *
     * @return list<array{code: string, message: string}>
     */
    public function check(int $orderId): array
    {
        return DB::transaction(function () use ($orderId): array {
            $order = Order::withTrashed()->whereKey($orderId)->lockForUpdate()->first();

            return $order === null
                ? [['code' => 'not_found', 'message' => 'The order no longer exists.']]
                : $this->reasonsForLockedOrder($order);
        });
    }

    /**
     * Call only inside a transaction that already holds this order's row lock.
     *
     * @return list<array{code: string, message: string}>
     */
    public function reasonsForLockedOrder(Order $order): array
    {
        $reasons = [];
        $add = function (string $code, string $message) use (&$reasons): void {
            $reasons[] = ['code' => $code, 'message' => $message];
        };

        if (! $order->trashed()) {
            $add('not_trashed', 'Only an order that is already in the trash can be permanently deleted.');
        }

        if ($order->status !== OrderStatus::CANCELLED) {
            $add('not_cancelled', "Only a cancelled order can be permanently deleted (this one is {$order->status->value}).");
        }

        if (Payment::where('order_id', $order->id)->exists()) {
            $add('payment_record', 'A payment was recorded for this order.');
        }

        if (in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)) {
            $add('payment_status', "The order's payment status is {$order->payment_status->value}.");
        }

        if (ReturnRequest::where('order_id', $order->id)->exists()) {
            $add('return_request', 'The order has a return or refund request.');
        }

        if (ReferralPointTransaction::where('order_id', $order->id)->exists()) {
            $add('loyalty_ledger', 'The order has loyalty or referral point transactions.');
        }

        if ((int) $order->points_redeemed > 0) {
            $add('points_redeemed', 'Loyalty points were redeemed on this order.');
        }

        if ($order->points_refunded_at !== null || $order->loyalty_points_granted_at !== null || $order->referral_reward_granted_at !== null) {
            $add('points_settled', 'Loyalty or referral points were refunded or granted for this order.');
        }

        if ($order->used_referral_discount || User::withTrashed()->where('referral_discount_used_order_id', $order->id)->exists()) {
            $add('referral_discount', 'A referral discount was used on this order.');
        }

        if ($order->coupon_id !== null || $order->coupon_code !== null || CouponUse::where('order_id', $order->id)->exists()) {
            $add('coupon', 'A coupon was applied to this order.');
        }

        if ((float) $order->discount > 0) {
            $add('discount', 'The order received a discount.');
        }

        if ($order->delivered_at !== null || in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::REFUNDED], true)) {
            $add('fulfilled', 'The order was delivered or refunded.');
        }

        if ($order->pathao_consignment_id !== null || $order->steadfast_consignment_id !== null
            || CourierSubmission::where('order_id', $order->id)->exists()) {
            $add('courier_history', 'The order was sent to, or attempted with, a courier.');
        }

        if (ReceivedSms::where('matched_order_id', $order->id)->exists()) {
            $add('payment_sms', 'A mobile-banking payment SMS is matched to this order.');
        }

        if (! $this->inventoryFullyRestored($order)) {
            $add('inventory_history', 'Stock moved for this order has not been fully and verifiably restored.');
        }

        if (Review::withTrashed()->where('order_id', $order->id)->exists()) {
            $add('reviews', 'The order has customer reviews.');
        }

        return $reasons;
    }

    /**
     * Stock movements don't block deletion only when they provably net to
     * zero: the cancellation restock ran (stock_restored_at), every line was
     * restocked in full, the order's movements are only the checkout OUT and
     * the restock RELEASE, and for each product/variant they balance. The
     * inventory_logs rows are never deleted (no FK to orders) and are copied
     * into the deletion audit. No movements at all → nothing to account for.
     */
    private function inventoryFullyRestored(Order $order): bool
    {
        $logs = InventoryLog::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->get(['product_id', 'variant_id', 'type', 'quantity']);

        if ($logs->isEmpty()) {
            return true;
        }

        if ($order->stock_restored_at === null
            || OrderItem::where('order_id', $order->id)->whereColumn('restocked_quantity', '<', 'quantity')->exists()
            || $logs->contains(fn (InventoryLog $log) => ! in_array($log->type, [InventoryType::OUT, InventoryType::RELEASE], true))) {
            return false;
        }

        return $logs
            ->groupBy(fn (InventoryLog $log) => $log->product_id.':'.($log->variant_id ?? '-'))
            ->every(fn ($movements) => (int) $movements->where('type', InventoryType::OUT)->sum('quantity')
                === (int) $movements->where('type', InventoryType::RELEASE)->sum('quantity'));
    }
}
