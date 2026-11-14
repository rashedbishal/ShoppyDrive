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
use Illuminate\Support\Str;

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

    private const DEFAULT_POINTS_VALUE_BDT = 1.0;

    /**
     * DISC-36: what each referral setting falls back to while its Setting row
     * has never been saved (the same fallbacks the getters below use), so
     * Admin > Settings > Referral & Loyalty shows the value actually in effect
     * instead of 0. LoyaltyPointsService::SETTING_DEFAULTS covers the rest.
     *
     * @var array<string, bool|int|float|string>
     */
    public const SETTING_DEFAULTS = [
        'referral_enabled' => false,
        'referral_discount_type' => 'percent',
        'referral_discount_value' => 0,
        'referral_min_order_amount' => 0,
        'referral_reward_points' => self::DEFAULT_REWARD_POINTS,
        'referral_reward_delay_days' => self::DEFAULT_REWARD_DELAY_DAYS,
        'referral_unlock_order_count' => self::DEFAULT_UNLOCK_ORDER_COUNT,
        'referral_reward_cap_amount' => self::DEFAULT_REWARD_CAP_AMOUNT,
        'referral_reward_cap_period' => self::DEFAULT_REWARD_CAP_PERIOD,
        'points_redeem_value_bdt' => self::DEFAULT_POINTS_VALUE_BDT,
    ];

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
        // DB-001 (L6): trashed orders still count, so soft deletion can never
        // make a customer look like they have fewer qualifying orders.
        return Order::withTrashed()->where('user_id', $user->id)
            ->where('status', OrderStatus::DELIVERED)
            ->count();
    }

    /**
     * Whether this user still qualifies for the one-time referred-customer
     * discount on their next order.
     *
     * @param  array<string, mixed>|null  $shippingAddress
     */
    public function isEligibleForDiscount(?User $user, ?string $phone = null, ?array $shippingAddress = null): bool
    {
        if ($user === null || ! $this->isEnabled()) {
            return false;
        }

        return $user->referred_by_id !== null
            && $user->referral_discount_used_order_id === null
            && ! $this->referralDiscountUsedByPhone($phone, $user->id)
            && ! $this->referralDiscountUsedByAddress($shippingAddress, $user->id);
    }

    /**
     * BIZ-024: the one-time referred-customer discount was tied only to the
     * account, so the same person could claim it again with each new email.
     * By owner decision it is refused once another account already has a
     * referral discount on record for an order with this phone (lenient:
     * an earlier ordinary order with the phone doesn't count). A cancelled
     * or refunded order gives its discount back (handleOrderCancelledOrRefunded()
     * clears referral_discount_used_order_id), so it no longer counts.
     */
    public function referralDiscountUsedByPhone(?string $phone, int $excludeUserId): bool
    {
        if ($phone === null || $phone === '') {
            return false;
        }

        return Order::withTrashed()
            ->where('used_referral_discount', true)
            ->where('shipping_address->phone', $phone)
            ->whereIn('id', User::withTrashed()
                ->whereNotNull('referral_discount_used_order_id')
                ->whereKeyNot($excludeUserId)
                ->select('referral_discount_used_order_id'))
            ->exists();
    }

    /**
     * BIZ-024: a different phone number alone doesn't stop the same person
     * re-claiming the one-time discount under a new email — but a physical
     * delivery address is far harder to vary cheaply. Matched the same
     * lenient way as the phone check (only a recorded *discount-using* order
     * counts, and a cancelled/refunded one gives it back), on the normalized
     * address_line1 + city + state (whitespace/case/punctuation-insensitive,
     * per owner decision) rather than a raw string, since two genuine
     * customers almost never type the exact same characters for the same
     * place, and the normalized set is always this small (one candidate
     * order per account that has ever used the discount), so comparing in
     * PHP avoids depending on JSON/LOWER/TRIM behaving identically across
     * MySQL (production) and SQLite (tests).
     *
     * @param  array<string, mixed>|null  $shippingAddress
     */
    public function referralDiscountUsedByAddress(?array $shippingAddress, int $excludeUserId): bool
    {
        $normalized = self::normalizeAddressForMatch($shippingAddress);

        if ($normalized === null) {
            return false;
        }

        return Order::withTrashed()
            ->where('used_referral_discount', true)
            ->whereIn('id', User::withTrashed()
                ->whereNotNull('referral_discount_used_order_id')
                ->whereKeyNot($excludeUserId)
                ->select('referral_discount_used_order_id'))
            ->get(['shipping_address'])
            ->contains(fn (Order $order) => self::normalizeAddressForMatch($order->shipping_address) === $normalized);
    }

    /**
     * A stable, whitespace/case/punctuation-insensitive key for
     * address_line1 + city + state, or null when any of the three is
     * missing/blank — an incomplete address never counts as a match either
     * way, rather than matching everything else that's also incomplete.
     *
     * @param  array<string, mixed>|null  $address
     */
    private static function normalizeAddressForMatch(?array $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $parts = array_map(function (mixed $part): ?string {
            if (! is_string($part) || trim($part) === '') {
                return null;
            }

            $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', Str::lower(trim($part)));
            $clean = trim(preg_replace('/\s+/', ' ', (string) $clean));

            return $clean === '' ? null : $clean;
        }, [$address['address_line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null]);

        if (in_array(null, $parts, true)) {
            return null;
        }

        return implode('|', $parts);
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
     *
     * The query above is only a candidate pre-filter — `withoutOverlapping()`
     * on the scheduled command guards the scheduler's own dispatch, not a
     * manually-run invocation overlapping it, so each order is re-fetched
     * and locked fresh inside its own transaction (retried up to 3 times on
     * deadlock), re-checking `status === DELIVERED`, `used_referral_discount`,
     * and `referral_reward_granted_at` on that locked row before anything is
     * granted. The referrer's id is discovered via a plain read on
     * `referred_by_id` — safe because that column is immutable once set at
     * registration (see `handleOrderCancelledOrRefunded()`), so this lookup
     * isn't racy — then both user rows are locked together in a single
     * ascending-id-ordered statement. Locking by ascending id, rather than a
     * fixed "referred, then referrer" role order, matters because it can
     * never deadlock against `handleOrderCancelledOrRefunded()` processing a
     * *different* order where the same two people hold the opposite roles (a
     * mutual referral: A referred B on one order, B referred A on another).
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

        $dueOrderIds = Order::where('used_referral_discount', true)
            ->where('status', OrderStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->whereNull('referral_reward_granted_at')
            ->where('delivered_at', '<=', $cutoff)
            ->orderBy('delivered_at')
            ->pluck('id');

        $granted = 0;

        foreach ($dueOrderIds as $orderId) {
            $outcome = DB::transaction(function () use ($orderId, $points, $pointsValue, $capAmount, $capPeriod): string {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();

                if ($order === null
                    || $order->status !== OrderStatus::DELIVERED
                    || ! $order->used_referral_discount
                    || $order->referral_reward_granted_at !== null
                    || $order->user_id === null) {
                    return 'skip';
                }

                $referredUserId = $order->user_id;
                $preliminaryReferrerId = User::whereKey($referredUserId)->value('referred_by_id');

                $lockIds = array_unique(array_filter([$referredUserId, $preliminaryReferrerId]));
                sort($lockIds);

                $lockedUsers = User::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

                $referredUser = $lockedUsers->get($referredUserId);

                if ($referredUser === null || $referredUser->referred_by_id === null) {
                    // No referrer to reward — mark it done so it's never reconsidered.
                    $order->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                    return 'marked_no_referrer';
                }

                // $referredUser->referred_by_id is effectively write-once, so it
                // should already be among $lockedUsers from the preliminary peek
                // above — this fallback only matters if that somehow changed
                // between the peek and the lock.
                $referrer = $lockedUsers->get($referredUser->referred_by_id)
                    ?? User::whereKey($referredUser->referred_by_id)->lockForUpdate()->first();

                if ($referrer === null) {
                    $order->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                    return 'marked_no_referrer';
                }

                $rewardValue = round($points * $pointsValue, 2);

                if ($capAmount > 0 && $rewardValue > 0) {
                    // Second-pass review finding: the referrer's row is already
                    // locked above, but *this* query is a plain read — and by
                    // this point in the transaction, the earlier plain read
                    // (discovering the referrer id via ->value('referred_by_id'))
                    // already established this transaction's REPEATABLE READ
                    // snapshot, before the user-row lock above was even
                    // contended. A concurrent transaction processing a
                    // *different* order for this same referrer, serialized
                    // behind that same lock, could therefore still see a
                    // stale "already earned" figure here once unblocked.
                    // ->lockForUpdate() makes this a locking ("current") read,
                    // which InnoDB always resolves against the latest
                    // committed data regardless of snapshot timing — the same
                    // property that makes every other locking read in this
                    // file correct. The user-row lock already acquired above
                    // provides the actual mutual exclusion between two such
                    // transactions; this only fixes what the second one sees
                    // once that exclusion lets it proceed.
                    $alreadyEarned = (float) ReferralPointTransaction::where('user_id', $referrer->id)
                        ->where('type', PointTransactionType::EARN_REFERRAL)
                        ->where('created_at', '>=', $this->periodStart($capPeriod))
                        ->lockForUpdate()
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
                    'order_id' => $order->id,
                    'type' => PointTransactionType::EARN_REFERRAL,
                    'points' => $points,
                    'currency_amount' => $rewardValue,
                    'note' => 'Referral bonus for '.$referredUser->name."'s order {$order->order_number}.",
                ]);

                $order->forceFill(['referral_reward_granted_at' => now()])->saveQuietly();

                return 'granted';
            }, 3);

            if ($outcome === 'granted') {
                $granted++;
            }
        }

        return $granted;
    }

    public function pointsValue(): float
    {
        return max(0.0, (float) (Setting::find('points_redeem_value_bdt')?->value ?? self::DEFAULT_POINTS_VALUE_BDT));
    }

    /**
     * Undo the one-time discount's effect on an order that just transitioned
     * to cancelled/refunded, restoring the referred user's eligibility to
     * use it again on a future order. If the referrer's points had already
     * been granted for this order (the return happened after the reward
     * delay had already elapsed), revoke them too.
     *
     * Runs inside one transaction (retried up to 3 times on deadlock),
     * locking the Order row first — a real mutex against
     * `grantDueReferralRewards()` processing the same order — then both
     * user rows together in a single ascending-id-ordered statement. The
     * referrer's id is read via a plain lookup on `referred_by_id`, which is
     * safe because that column is immutable once set at registration — no
     * code path updates it after `resolveReferrerId()` establishes it for a
     * new account, so a plain read here is never racy. See the comment on
     * `grantDueReferralRewards()` for why ascending-id locking (rather than
     * a fixed role order) matters here too. A bare `lockForUpdate()` outside
     * a transaction (the pre-fix shape) can't actually serialize two
     * concurrent callers — e.g. a courier webhook racing an admin
     * cancellation — since the lock only does anything useful while held
     * inside an open transaction.
     */
    public function handleOrderCancelledOrRefunded(Order $order): void
    {
        if (! $order->used_referral_discount || $order->user_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null) {
                return;
            }

            $referredUserId = $lockedOrder->user_id;
            $preliminaryReferrerId = User::whereKey($referredUserId)->value('referred_by_id');

            $lockIds = array_unique(array_filter([$referredUserId, $preliminaryReferrerId]));
            sort($lockIds);

            $lockedUsers = User::whereIn('id', $lockIds)->lockForUpdate()->get()->keyBy('id');

            $referredUser = $lockedUsers->get($referredUserId);

            if ($referredUser === null || $referredUser->referral_discount_used_order_id !== $lockedOrder->id) {
                // Already reverted, or this order never actually ended up being
                // the one on record (defensive no-op — keeps this idempotent).
                return;
            }

            $referredUser->forceFill(['referral_discount_used_order_id' => null])->save();

            if ($lockedOrder->referral_reward_granted_at === null || $referredUser->referred_by_id === null) {
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

            $earned = (int) ReferralPointTransaction::where('order_id', $lockedOrder->id)
                ->where('type', PointTransactionType::EARN_REFERRAL)
                ->sum('points');

            // DISC-11 (owner rule, 2026-11-13): the full reward is reversed even
            // if the referrer already spent it, so the balance may go negative;
            // later earnings offset it first and a negative balance is never
            // spendable (LoyaltyPointsService::availablePoints()). Anything
            // already reversed for this order is subtracted, so the ledger
            // itself also makes the reversal exactly-once.
            $alreadyRevoked = -1 * (int) ReferralPointTransaction::where('order_id', $lockedOrder->id)
                ->where('user_id', $referrer->id)
                ->where('type', PointTransactionType::REVOKE)
                ->sum('points');

            $toRevoke = $earned - $alreadyRevoked;

            if ($toRevoke <= 0) {
                return;
            }

            $referrer->decrement('points_balance', $toRevoke);

            ReferralPointTransaction::create([
                'user_id' => $referrer->id,
                'referred_user_id' => $referredUser->id,
                'order_id' => $lockedOrder->id,
                'type' => PointTransactionType::REVOKE,
                'points' => -$toRevoke,
                'note' => 'Order '.$lockedOrder->order_number.' was cancelled/refunded — referral bonus reversed.',
            ]);
        }, 3);
    }

    /**
     * Asia/Dhaka calendar start of the current reward-cap period, in the
     * application timezone: dates reach SQL as plain wall-clock strings, so
     * it must match how `created_at` is stored.
     */
    private function periodStart(string $period): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Dhaka');
        $start = $period === 'week' ? $now->startOfWeek() : $now->startOfMonth();

        return $start->setTimezone(config('app.timezone'));
    }
}
