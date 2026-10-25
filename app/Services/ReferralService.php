<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\PointTransactionType;
use App\Models\Order;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;

class ReferralService
{
    /**
     * Resolve a referral code typed/pre-filled at registration into the
     * referring user's id. Returns null for a blank/unknown code, and also
     * returns null outright while the referral program is disabled so no
     * new referral relationship is created while the CMS toggle is off.
     */
    public function resolveReferrerId(?string $code): ?int
    {
        $code = $code !== null ? trim($code) : null;

        if ($code === '' || $code === null || ! $this->isEnabled()) {
            return null;
        }

        return User::where('referral_code', strtoupper($code))->value('id');
    }

    public function isEnabled(): bool
    {
        return (bool) (Setting::find('referral_enabled')?->value ?? false);
    }

    /**
     * Whether this user still qualifies for the one-time referred-customer
     * discount on their next order.
     */
    public function isEligibleForDiscount(?User $user): bool
    {
        if ($user === null || ! $this->isEnabled()) {
            return false;
        }

        return $user->referred_by_id !== null && $user->referral_discount_used_order_id === null;
    }

    public function calculateReferralDiscount(User $user, float $subtotal): float
    {
        if (! $this->isEligibleForDiscount($user)) {
            return 0.0;
        }

        $type = CouponType::tryFrom((string) (Setting::find('referral_discount_type')?->value ?? 'percent')) ?? CouponType::PERCENT;
        $value = (float) (Setting::find('referral_discount_value')?->value ?? 0);

        if ($value <= 0) {
            return 0.0;
        }

        $discount = $type === CouponType::PERCENT
            ? $subtotal * $value / 100
            : $value;

        return round(min($subtotal, $discount), 2);
    }

    /**
     * Lock the purchaser's row for the duration of the order-creation
     * transaction, so concurrent checkouts by the same user can't double
     * consume the referral discount or double-redeem/award points.
     */
    public function lockUser(int $userId): User
    {
        return User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Credit the referrer once their referred user's qualifying (first,
     * discount-using) order has been created. Call this on the LOCKED
     * referred user, inside the same transaction that created the order.
     * Idempotent: a referred user can only ever have one order recorded as
     * having used the discount.
     */
    public function awardPointsForQualifyingOrder(Order $order, User $lockedReferredUser): void
    {
        if (! $order->used_referral_discount || $lockedReferredUser->referral_discount_used_order_id !== null) {
            return;
        }

        $lockedReferredUser->forceFill(['referral_discount_used_order_id' => $order->id])->save();

        if ($lockedReferredUser->referred_by_id === null) {
            return;
        }

        $points = (int) (Setting::find('referral_points_per_referral')?->value ?? 0);

        if ($points <= 0) {
            return;
        }

        $referrer = User::whereKey($lockedReferredUser->referred_by_id)->lockForUpdate()->first();

        if ($referrer === null) {
            return;
        }

        $referrer->increment('points_balance', $points);

        ReferralPointTransaction::create([
            'user_id' => $referrer->id,
            'referred_user_id' => $lockedReferredUser->id,
            'order_id' => $order->id,
            'type' => PointTransactionType::EARN,
            'points' => $points,
            'note' => 'Referral bonus for '.$lockedReferredUser->name."'s first order.",
        ]);
    }

    /**
     * Currency value of redeeming N points at today's conversion rate.
     * Pure calculation — does not touch the database. Call on the LOCKED
     * user so the balance check reflects any concurrent transaction.
     */
    public function redeemPoints(User $lockedUser, int $points): float
    {
        if ($points <= 0 || $lockedUser->points_balance < $points) {
            return 0.0;
        }

        $rate = (float) (Setting::find('referral_points_value')?->value ?? 0);

        return round($points * $rate, 2);
    }

    /**
     * Debit redeemed points from the LOCKED user and record the order that
     * spent them. Call inside the same transaction that created the order.
     */
    public function debitPointsForOrder(Order $order, User $lockedUser, int $points, float $amount): void
    {
        if ($points <= 0) {
            return;
        }

        $lockedUser->decrement('points_balance', $points);

        ReferralPointTransaction::create([
            'user_id' => $lockedUser->id,
            'order_id' => $order->id,
            'type' => PointTransactionType::REDEEM,
            'points' => -$points,
            'currency_amount' => $amount,
            'note' => 'Redeemed at checkout on order '.$order->order_number.'.',
        ]);
    }

    /**
     * Undo the referral side-effects of an order that just transitioned to
     * cancelled/refunded. Two independent branches: restoring the referred
     * user's one-time discount eligibility and revoking the points it
     * earned the referrer, and refunding any points the order itself spent.
     */
    public function handleOrderCancelledOrRefunded(Order $order): void
    {
        $this->revokeReferralRewardIfApplicable($order);
        $this->refundRedeemedPointsIfApplicable($order);
    }

    private function revokeReferralRewardIfApplicable(Order $order): void
    {
        if (! $order->used_referral_discount) {
            return;
        }

        $referredUser = User::whereKey($order->user_id)->lockForUpdate()->first();

        if ($referredUser === null || $referredUser->referral_discount_used_order_id !== $order->id) {
            // Already reverted, or this order never actually ended up being
            // the one on record (defensive no-op — keeps this idempotent).
            return;
        }

        $referredUser->forceFill(['referral_discount_used_order_id' => null])->save();

        if ($referredUser->referred_by_id === null) {
            return;
        }

        $earned = ReferralPointTransaction::where('order_id', $order->id)
            ->where('type', PointTransactionType::EARN)
            ->sum('points');

        if ($earned <= 0) {
            return;
        }

        $referrer = User::whereKey($referredUser->referred_by_id)->lockForUpdate()->first();

        if ($referrer === null) {
            return;
        }

        $toRevoke = min($earned, $referrer->points_balance);
        $referrer->decrement('points_balance', $toRevoke);

        ReferralPointTransaction::create([
            'user_id' => $referrer->id,
            'referred_user_id' => $referredUser->id,
            'order_id' => $order->id,
            'type' => PointTransactionType::REVOKE,
            'points' => -$toRevoke,
            'note' => 'Order '.$order->order_number.' was cancelled/refunded — referral bonus reversed.',
        ]);
    }

    private function refundRedeemedPointsIfApplicable(Order $order): void
    {
        if (! $order->points_redeemed || $order->points_refunded_at !== null || $order->user_id === null) {
            return;
        }

        $purchaser = User::whereKey($order->user_id)->lockForUpdate()->first();

        if ($purchaser === null) {
            return;
        }

        $purchaser->increment('points_balance', $order->points_redeemed);

        ReferralPointTransaction::create([
            'user_id' => $purchaser->id,
            'order_id' => $order->id,
            'type' => PointTransactionType::REFUND,
            'points' => $order->points_redeemed,
            'note' => 'Order '.$order->order_number.' was cancelled/refunded — redeemed points refunded.',
        ]);

        $order->forceFill(['points_refunded_at' => now()])->saveQuietly();
    }
}
