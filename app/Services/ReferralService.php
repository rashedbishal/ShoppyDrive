<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Models\Order;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    /**
     * Fallback values used whenever the admin hasn't (yet) set the matching
     * Setting row — chosen so the whole program behaves sensibly out of the
     * box instead of silently granting nothing (or, worse, dividing by zero)
     * until every knob has been visited once in Admin > Settings.
     */
    private const DEFAULT_REWARD_POINTS = 100;

    private const DEFAULT_REWARD_DELAY_DAYS = 7;

    private const DEFAULT_UNLOCK_ORDER_COUNT = 1;

    private const DEFAULT_REWARD_CAP_AMOUNT = 500.0;

    private const DEFAULT_REWARD_CAP_PERIOD = 'month';

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
     * Whether this user has placed enough of their own delivered orders to
     * unlock sharing their invite link/code. Shown on the rewards page —
     * callers should hide the referral code/link entirely while this is
     * false, not just the reward amounts.
     */
    public function hasUnlockedInviteLink(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->deliveredOrderCount($user) >= $this->unlockOrderCount();
    }

    public function unlockOrderCount(): int
    {
        return max(0, (int) (Setting::find('referral_unlock_order_count')?->value ?? self::DEFAULT_UNLOCK_ORDER_COUNT));
    }

    public function deliveredOrderCount(User $user): int
    {
        return Order::where('user_id', $user->id)
            ->where('status', OrderStatus::DELIVERED)
            ->count();
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

    public function minOrderAmountForDiscount(): float
    {
        return max(0.0, (float) (Setting::find('referral_min_order_amount')?->value ?? 0));
    }

    /**
     * Zero below the configured minimum order amount — the referred
     * customer's one-time discount only ever applies to a qualifying order,
     * and returning 0 here (rather than throwing/blocking checkout) leaves
     * their eligibility untouched for a later, larger order.
     */
    public function calculateReferralDiscount(User $user, float $subtotal): float
    {
        if (! $this->isEligibleForDiscount($user) || $subtotal < $this->minOrderAmountForDiscount()) {
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
     * consume the referral discount or double-redeem points.
     */
    public function lockUser(int $userId): User
    {
        return User::whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Mark the referred user's one-time discount as spent the moment their
     * qualifying order is placed. Call on the LOCKED purchaser, inside the
     * same transaction that created the order. Idempotent: a referred user
     * can only ever have one order recorded as having used the discount.
     *
     * This does NOT grant the referrer anything — that only happens later,
     * once the order is actually delivered and has sat past the return
     * window; see `grantDueReferralRewards()`.
     */
    public function markReferralDiscountUsed(Order $order, User $lockedReferredUser): void
    {
        if (! $order->used_referral_discount || $lockedReferredUser->referral_discount_used_order_id !== null) {
            return;
        }

        $lockedReferredUser->forceFill(['referral_discount_used_order_id' => $order->id])->save();
    }

    public function rewardDelayDays(): int
    {
        return max(0, (int) (Setting::find('referral_reward_delay_days')?->value ?? self::DEFAULT_REWARD_DELAY_DAYS));
    }

    public function rewardPoints(): int
    {
        return max(0, (int) (Setting::find('referral_reward_points')?->value ?? self::DEFAULT_REWARD_POINTS));
    }

    public function rewardCapAmount(): float
    {
        return max(0.0, (float) (Setting::find('referral_reward_cap_amount')?->value ?? self::DEFAULT_REWARD_CAP_AMOUNT));
    }

    /** @return 'week'|'month' */
    public function rewardCapPeriod(): string
    {
        $period = (string) (Setting::find('referral_reward_cap_period')?->value ?? self::DEFAULT_REWARD_CAP_PERIOD);

        return $period === 'week' ? 'week' : 'month';
    }

    /**
     * Credit every referrer whose referred customer's qualifying order has
     * now sat delivered for at least `referral_reward_delay_days` — long
     * enough to cover the return window — without being cancelled/returned
     * (both collapse to `OrderStatus::CANCELLED`/`REFUNDED` in this app, so
     * simply re-checking the order is still `DELIVERED` covers both).
     *
     * Intended to run on a schedule (see `rewards:grant-due`), not inline
     * during checkout — rewards here are inherently delayed, so there's no
     * request to hang this off of.
     *
     * A referrer who has already hit their per-period reward cap is skipped
     * for this run and reconsidered on the next one, once the period rolls
     * over — the reward is deferred, never forfeited.
     */
    public function grantDueReferralRewards(): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $points = $this->rewardPoints();

        if ($points <= 0) {
            return 0;
        }

        $delayDays = $this->rewardDelayDays();
        $cutoff = CarbonImmutable::now('Asia/Dhaka')->subDays($delayDays);
        $pointsValue = $this->pointsValue();
        $capAmount = $this->rewardCapAmount();
        $capPeriod = $this->rewardCapPeriod();

        $dueOrders = Order::where('used_referral_discount', true)
            ->where('status', OrderStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->whereNull('referral_reward_granted_at')
            ->where('delivered_at', '<=', $cutoff)
            ->orderBy('delivered_at')
            ->get();

        $granted = 0;

        // The query above is only a candidate pre-filter — `withoutOverlapping()`
        // on the scheduled command guards the scheduler's own dispatch, not a
        // manually-run invocation overlapping it, so each candidate order is
        // re-verified against a freshly locked row inside its own transaction
        // before anything is actually granted. When both the referred user and
        // referrer need locking, both are locked in a single `whereIn()` query
        // in ascending-id order, so two concurrent runs can't form a lock-
        // ordering cycle over the same pair of users.
        foreach ($dueOrders as $order) {
            $outcome = DB::transaction(function () use ($order, $points, $pointsValue, $capAmount, $capPeriod): string {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                if ($locked === null
                    || $locked->status !== OrderStatus::DELIVERED
                    || ! $locked->used_referral_discount
                    || $locked->referral_reward_granted_at !== null
                    || $locked->user_id === null) {
                    return 'skip';
                }

                $referredUserId = $locked->user_id;
                $preliminaryReferrerId = User::whereKey($referredUserId)->value('referred_by_id');

                $lockIds = array_unique(array_filter([$referredUserId, $preliminaryReferrerId]));
                sort($lockIds);

                $lockedUsers = User::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

                $referredUser = $lockedUsers->get($referredUserId);

                if ($referredUser === null || $referredUser->referred_by_id === null) {
                    // No referrer to reward — mark it done so it's never reconsidered.
                    $locked->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                    return 'marked_no_referrer';
                }

                // $referredUser->referred_by_id is effectively write-once, so it
                // should already be among $lockedUsers from the preliminary peek
                // above — this fallback only matters if that somehow changed
                // between the peek and the lock.
                $referrer = $lockedUsers->get($referredUser->referred_by_id)
                    ?? User::whereKey($referredUser->referred_by_id)->lockForUpdate()->first();

                if ($referrer === null) {
                    $locked->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                    return 'marked_no_referrer';
                }

                $rewardValue = round($points * $pointsValue, 2);

                if ($capAmount > 0 && $rewardValue > 0) {
                    $alreadyEarned = (float) ReferralPointTransaction::where('user_id', $referrer->id)
                        ->where('type', PointTransactionType::EARN_REFERRAL)
                        ->where('created_at', '>=', $this->periodStart($capPeriod))
                        ->sum('currency_amount');

                    if ($alreadyEarned + $rewardValue > $capAmount) {
                        // Cap reached for this period — leave ungranted, retry on a later run.
                        return 'cap_reached';
                    }
                }

                $referrer->increment('points_balance', $points);

                ReferralPointTransaction::create([
                    'user_id' => $referrer->id,
                    'referred_user_id' => $referredUser->id,
                    'order_id' => $locked->id,
                    'type' => PointTransactionType::EARN_REFERRAL,
                    'points' => $points,
                    'currency_amount' => $rewardValue,
                    'note' => 'Referral bonus for '.$referredUser->name."'s order {$locked->order_number}.",
                ]);

                $locked->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                return 'granted';
            });

            if ($outcome === 'granted') {
                $granted++;
            }
        }

        return $granted;
    }

    public function pointsValue(): float
    {
        return max(0.0, (float) (Setting::find('points_redeem_value_bdt')?->value ?? 1.0));
    }

    /**
     * Undo the one-time discount's effect on an order that just transitioned
     * to cancelled/refunded, restoring the referred user's eligibility to
     * use it again on a future order. If the referrer's points had already
     * been granted for this order (the return happened after the reward
     * delay had already elapsed), revoke them too.
     */
    /**
     * Wrapped in its own transaction with every row this touches locked
     * fresh inside it — a bare `lockForUpdate()` outside a transaction only
     * acquires and releases its lock within MySQL's own implicit per-
     * statement autocommit, so it can't actually serialize two concurrent
     * callers (e.g. a courier webhook racing an admin cancellation) the way
     * the original code's shape implied. When both the referred user and
     * the referrer need locking, both rows are locked in a single
     * `whereIn()` query in ascending-id order, so two concurrent reversals
     * that happen to involve the same two users in swapped roles can never
     * form a lock-ordering cycle.
     */
    public function handleOrderCancelledOrRefunded(Order $order): void
    {
        if (! $order->used_referral_discount || $order->user_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $referredUserId = $order->user_id;
            $preliminaryReferrerId = User::whereKey($referredUserId)->value('referred_by_id');

            $lockIds = array_unique(array_filter([$referredUserId, $preliminaryReferrerId]));
            sort($lockIds);

            $lockedUsers = User::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

            $referredUser = $lockedUsers->get($referredUserId);

            if ($referredUser === null || $referredUser->referral_discount_used_order_id !== $order->id) {
                // Already reverted, or this order never actually ended up being
                // the one on record (defensive no-op — keeps this idempotent).
                return;
            }

            $referredUser->forceFill(['referral_discount_used_order_id' => null])->save();

            // Re-read rather than trust `$order`'s in-memory attribute: the Eloquent
            // instance that triggered this (from `Order::booted()`) may have been
            // fetched before `grantDueReferralRewards()` last touched this column.
            $rewardGranted = Order::whereKey($order->id)->value('referral_reward_granted_at') !== null;

            if (! $rewardGranted || $referredUser->referred_by_id === null) {
                return;
            }

            $earned = ReferralPointTransaction::where('order_id', $order->id)
                ->where('type', PointTransactionType::EARN_REFERRAL)
                ->sum('points');

            if ($earned <= 0) {
                return;
            }

            // $referredUser->referred_by_id is effectively write-once, so it
            // should already be among $lockedUsers from the preliminary peek
            // above — this fallback only matters if that somehow changed
            // between the peek and the lock.
            $referrer = $lockedUsers->get($referredUser->referred_by_id)
                ?? User::whereKey($referredUser->referred_by_id)->lockForUpdate()->first();

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
        });
    }

    /** Asia/Dhaka calendar start of the current reward-cap period. */
    private function periodStart(string $period): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Dhaka');

        return $period === 'week' ? $now->startOfWeek() : $now->startOfMonth();
    }
}
