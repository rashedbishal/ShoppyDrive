<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CouponUnavailableException;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Coupon usage (used_count / CouponUse rows) is counted at the same
 * lifecycle moment as every other order-creation side effect in this app
 * (stock decrement, referral discount, loyalty points) — when the Order
 * row is actually created, not when the coupon is validated/previewed at
 * checkout submission. This mirrors the existing, already-correct
 * referral/points pattern (ReferralService::markReferralDiscountUsed(),
 * LoyaltyPointsService::debitPointsForOrder()), which already run inside
 * the same transaction, under the same purchaser lock, right alongside
 * order-item creation in every order-creation path.
 *
 * A consequence of counting at order-creation rather than at checkout
 * submission: a coupon applied to a mobile-banking/ZiniPay PendingCheckout
 * that then expires, fails, or is abandoned never incremented anything in
 * the first place — there is nothing to release and no reservation table
 * or scheduled cleanup job is needed.
 */
class CouponService
{
    /**
     * Must run inside the caller's own order-creation DB::transaction() —
     * same requirement as InventoryService::decrementForOrderItem(). Only
     * re-validates the two count-based, race-sensitive caps (max_uses,
     * per_user_limit) under a row lock; date-window/active-status were
     * already checked at the (unlocked) preview stage in resolveCoupon()
     * and are not re-litigated here, for the same reason flash-sale price
     * isn't re-validated at mobile-banking confirm time — the discount was
     * already quoted to and paid by the customer.
     *
     * @throws CouponUnavailableException
     */
    public function consume(int $couponId, Order $order, ?int $userId, float $discountAmount, ?string $guestPhone = null): void
    {
        $coupon = Coupon::whereKey($couponId)->lockForUpdate()->first();

        if ($coupon === null) {
            throw CouponUnavailableException::because('This coupon no longer exists.');
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            throw CouponUnavailableException::because('This coupon has just reached its usage limit.');
        }

        // lockForUpdate() here isn't just belt-and-suspenders: under
        // InnoDB's default REPEATABLE READ, a plain (non-locking) read
        // sees a snapshot taken when this transaction started, so two
        // concurrent consume() calls for the same user could each see
        // zero prior uses and both pass this check before either commits.
        // A locking read bypasses the snapshot and reads/locks the latest
        // committed rows, so the second caller either sees the first
        // caller's row (if it already committed) or blocks until it does
        // — the same correctness property Coupon::lockForUpdate() above
        // already relies on for max_uses.
        if ($coupon->per_user_limit !== null && $userId !== null && $coupon->uses()->where('user_id', $userId)->lockForUpdate()->count() >= $coupon->per_user_limit) {
            throw CouponUnavailableException::because('You have already used this coupon the maximum number of times.');
        }

        // PROMO-001: the guest equivalent of the check above — see
        // Coupon::guestRedemptionCount()'s docblock for why this is
        // derived from Order rows rather than a locked CouponUse count.
        if ($coupon->per_user_limit !== null && $userId === null && $guestPhone !== null
            && Coupon::guestRedemptionCount($coupon->id, $guestPhone, excludeOrderId: $order->id, lockForUpdate: true) >= $coupon->per_user_limit) {
            throw CouponUnavailableException::because('You have already used this coupon the maximum number of times.');
        }

        $coupon->increment('used_count');

        if ($userId !== null) {
            CouponUse::create([
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'order_id' => $order->id,
                'discount_amount' => $discountAmount,
            ]);
        }
    }

    /**
     * Reverses consumption for a cancelled/refunded order. Idempotent and
     * concurrency-safe via `coupon_released_at` under the same row lock —
     * see InventoryService::restoreForOrder()'s identical reasoning for
     * why this wraps itself in its own transaction rather than trusting
     * the caller (Order::booted(), which runs outside any transaction of
     * its own) to provide one.
     */
    public function release(Order $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }

        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->coupon_released_at !== null || $locked->coupon_id === null) {
                return;
            }

            $coupon = Coupon::whereKey($locked->coupon_id)->lockForUpdate()->first();

            if ($coupon !== null) {
                $coupon->decrement('used_count');
            }

            CouponUse::where('order_id', $locked->id)->delete();

            $locked->forceFill(['coupon_released_at' => now()])->saveQuietly();
        });
    }
}
