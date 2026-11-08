<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * The customer self-service cancel rule — routes/account.php's
 * `account.orders.cancel` and the AI assistant's cancel_order tool both
 * call this, so a customer can only ever cancel their own order under one
 * rule, enforced once. Deliberately narrower than
 * Admin\OrderController::cancel() (FSA-15: that one also blocks a paid,
 * shipped, or courier-held order with its own reason, and records a
 * cancellation reason) — this exists for the self-service "I don't want
 * this anymore" case before a courier is ever involved, not as a general
 * cancellation authority. Stock restoration, loyalty/referral reversal and
 * coupon release all still happen the same way regardless of which path
 * flips the status — see Order::booted()'s `updated()` hook, the one place
 * that actually runs.
 */
class OrderCancellationService
{
    public function __construct(private readonly CourierSubmissionService $courierSubmissions) {}

    /**
     * @return string|null An error message, or null on success.
     */
    public function cancelAsCustomer(Order $order, string $note = 'Cancelled by customer.'): ?string
    {
        return DB::transaction(function () use ($order, $note): ?string {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null || $lockedOrder->status !== OrderStatus::PENDING) {
                return 'Only pending orders can be cancelled.';
            }

            if ($lockedOrder->pathao_consignment_id !== null
                || $lockedOrder->steadfast_consignment_id !== null
                || $this->courierSubmissions->hasUnresolvedSubmission($lockedOrder->id)) {
                return 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.';
            }

            $lockedOrder->update(['status' => OrderStatus::CANCELLED]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => OrderStatus::CANCELLED->value,
                'notes' => $note,
            ]);

            return null;
        }, 3);
    }
}
