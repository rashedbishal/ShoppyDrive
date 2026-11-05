<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\CouldNotRecordRefundException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

/**
 * PAY-RETURN-001: the one place a refund is recorded. Return-based and
 * whole-order refunds share the order_refunds ledger and the same cap, under
 * the same Order row lock, so neither can bypass the other or overpay.
 *
 * Nothing here moves money — the admin records a refund already paid back
 * through bKash/bank/etc.; this only refuses records the customer was
 * never owed (unpaid orders, above the cap, unverified courier deliveries).
 */
class RefundService
{
    public function __construct(
        private readonly RefundCalculator $calculator,
        private readonly CourierEvidenceChain $chain,
        private readonly CourierSubmissionService $courierSubmissions,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    /**
     * @throws CouldNotRecordRefundException
     */
    public function recordReturnRefund(Admin $admin, ReturnRequest $returnRequest, float|int|string $amount, string $method): OrderRefund
    {
        $amountPaisa = RefundCalculator::toPaisa($amount);

        if ($amountPaisa < 0) {
            throw new CouldNotRecordRefundException('The refund amount cannot be negative.');
        }

        return DB::transaction(function () use ($admin, $returnRequest, $amountPaisa, $method): OrderRefund {
            // Order first, then the return request — the lock order every
            // return/refund action uses, so they serialize instead of deadlocking.
            $order = Order::whereKey($returnRequest->order_id)->lockForUpdate()->first();
            $locked = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();

            if ($order === null || $locked === null || $locked->status !== ReturnStatus::APPROVED) {
                throw new CouldNotRecordRefundException('Only an approved return request can be marked as refunded.');
            }

            if ($order->status === OrderStatus::REFUNDED) {
                throw new CouldNotRecordRefundException('This order has already been refunded as a whole — no further return refund can be recorded.');
            }

            $this->assertRefundable($order);

            $maximum = (int) $this->calculator->returnMaximumPaisa($order, $locked, lock: true);

            if ($amountPaisa > $maximum) {
                throw new CouldNotRecordRefundException('This return can be refunded at most ৳'.number_format(RefundCalculator::toTaka($maximum), 2).' — the discounted value of the returned items, within what the customer paid (shipping is not refundable).');
            }

            $locked->update([
                'status' => ReturnStatus::REFUNDED,
                'refund_amount' => RefundCalculator::toTaka($amountPaisa),
                'refund_method' => $method,
                'resolved_at' => now(),
            ]);

            $fullyReturned = ReturnRequest::isOrderFullyReturned($order);

            $refund = $this->record($admin, $order, $amountPaisa, $method, OrderRefund::SOURCE_RETURN, $locked->id, $fullyReturned,
                "Return request #{$locked->id} fully refunded the order — recorded by {$admin->name}.");

            if (! $fullyReturned) {
                $this->loyalty->reconcileEarnedPointsAfterRefund($order);
                // PAY-RETURN-001-BATCH-2: mirrors the call above — only
                // needed here because a fully-returned order already gets
                // this via Order::booted()'s REFUNDED-transition hook.
                $this->loyalty->reconcileRedeemedPointsAfterRefund($order);
            }

            return $refund;
        }, 3);
    }

    /**
     * Whole-order refund: the amount must equal exactly what is still
     * refundable (which also rejects a stale admin page).
     *
     * @throws CouldNotRecordRefundException
     */
    public function recordOrderRefund(Admin $admin, Order $order, float|int|string $amount, string $method): OrderRefund
    {
        $amountPaisa = RefundCalculator::toPaisa($amount);

        return DB::transaction(function () use ($admin, $order, $amountPaisa, $method): OrderRefund {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status === OrderStatus::REFUNDED) {
                throw new CouldNotRecordRefundException('This order is already marked as refunded.');
            }

            // COURIER-SEND-RACE-001: a parcel may exist (or be about to) at the courier.
            if ($this->courierSubmissions->hasUnresolvedSubmission($locked->id)) {
                throw new CouldNotRecordRefundException($this->courierSubmissions->unresolvedMessage('marked refunded'));
            }

            if (ReturnRequest::where('order_id', $locked->id)->whereIn('status', [ReturnStatus::PENDING, ReturnStatus::APPROVED])->exists()) {
                throw new CouldNotRecordRefundException('Resolve this order\'s pending or approved return requests before refunding the whole order.');
            }

            $this->assertRefundable($locked);

            $remaining = (int) $this->calculator->remainingPaisa($locked, lock: true);

            if ($remaining <= 0) {
                throw new CouldNotRecordRefundException('Nothing is left to refund on this order.');
            }

            if ($amountPaisa !== $remaining) {
                throw new CouldNotRecordRefundException('The refund amount must be exactly the remaining refundable amount, ৳'.number_format(RefundCalculator::toTaka($remaining), 2).'. Reload the page and try again.');
            }

            return $this->record($admin, $locked, $amountPaisa, $method, OrderRefund::SOURCE_ORDER, null, true,
                "Marked as refunded by {$admin->name} (was: {$locked->status->value}): ৳".number_format(RefundCalculator::toTaka($amountPaisa), 2)." via {$method}.");
        }, 3);
    }

    /**
     * @throws CouldNotRecordRefundException
     */
    private function assertRefundable(Order $order): void
    {
        // BIZ-005: a cancelled order can still be paid — the customer
        // cancel route and CourierOrderStatusMapper's return/cancel report
        // both cancel without checking payment_status, since forcing a
        // paid order to stay open just to keep this refundable would be
        // worse. Only block the combination with nothing to give back.
        if ($order->status === OrderStatus::CANCELLED && $order->payment_status !== PaymentStatus::PAID) {
            throw new CouldNotRecordRefundException('A cancelled order cannot be refunded.');
        }

        if ($order->payment_status !== PaymentStatus::PAID) {
            throw new CouldNotRecordRefundException('No payment was collected for this order, so nothing can be refunded. Cancel the order instead.');
        }

        if ($this->calculator->collectedPaisa($order) === null) {
            throw new CouldNotRecordRefundException('The amount collected for this order cannot be established, so no refund can be recorded.');
        }

        if ($this->calculator->hasUnrecordedRefunds($order)) {
            throw new CouldNotRecordRefundException('This order has a refund recorded before the refund ledger existed without an amount, so no further refund can be recorded.');
        }

        $courier = $this->chain->eligibility($order);

        if (! $courier['eligible']) {
            throw new CouldNotRecordRefundException('Courier verification is required before refunding this order: '.implode(' ', $courier['reasons']));
        }
    }

    /**
     * Writes the ledger row (its resulting statuses computed first — rows are
     * append-only), then applies the order/payment status change.
     */
    private function record(Admin $admin, Order $order, int $amountPaisa, string $method, string $source, ?int $returnRequestId, bool $closesOrder, string $historyNote): OrderRefund
    {
        $collected = (int) $this->calculator->collectedPaisa($order);
        $refundedAfter = $this->calculator->refundedPaisa($order, lock: true) + $amountPaisa;
        $paymentRefunded = $collected > 0 && $refundedAfter >= $collected;

        $previousStatus = $order->status;
        $previousPayment = $order->payment_status;
        $resultingStatus = $closesOrder ? OrderStatus::REFUNDED : $previousStatus;
        $resultingPayment = $paymentRefunded ? PaymentStatus::REFUNDED : $previousPayment;

        $refund = OrderRefund::create([
            'order_id' => $order->id,
            'return_request_id' => $returnRequestId,
            'source' => $source,
            'amount' => RefundCalculator::toTaka($amountPaisa),
            'method' => $method,
            'previous_order_status' => $previousStatus->value,
            'resulting_order_status' => $resultingStatus->value,
            'previous_payment_status' => $previousPayment->value,
            'resulting_payment_status' => $resultingPayment->value,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ]);

        $updates = [];

        if ($resultingStatus !== $previousStatus) {
            $updates['status'] = $resultingStatus;
        }

        if ($resultingPayment !== $previousPayment) {
            $updates['payment_status'] = $resultingPayment;
        }

        if ($updates !== []) {
            // A status change to REFUNDED runs Order::booted()'s side effects
            // (stock, coupon, referral, redeemed and earned points) inside this transaction.
            $order->update($updates);
        }

        if ($paymentRefunded) {
            Payment::where('order_id', $order->id)->where('status', PaymentStatus::PAID->value)->update(['status' => PaymentStatus::REFUNDED->value]);
        }

        if ($closesOrder) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderStatus::REFUNDED->value,
                'notes' => $historyNote,
            ]);
        }

        activity('orders')
            ->causedBy($admin)
            ->performedOn($order)
            ->event('refund.recorded')
            ->withProperties([
                'order_refund_id' => $refund->id,
                'return_request_id' => $returnRequestId,
                'amount' => $refund->amount,
                'method' => $method,
                'previous_order_status' => $previousStatus->value,
                'resulting_order_status' => $resultingStatus->value,
                'previous_payment_status' => $previousPayment->value,
                'resulting_payment_status' => $resultingPayment->value,
            ])
            ->log("Refund of ৳{$refund->amount} recorded for order {$order->order_number} by {$admin->name}.");

        return $refund;
    }
}
