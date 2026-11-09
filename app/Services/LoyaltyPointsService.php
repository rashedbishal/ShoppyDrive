<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\PendingCheckout;
use App\Models\ReferralPointTransaction;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The general points ledger: earning (orders, registration, reviews, daily
 * login), redeeming at checkout, and reversing both on cancellation/refund.
 * Referral-specific calculations (discount eligibility, the delayed
 * referrer reward) live in `ReferralService`; this service is what actually
 * touches `User::points_balance` and writes `ReferralPointTransaction` rows
 * for every source except the referral reward itself, which `ReferralService`
 * writes directly since granting it is wholly a referral concern.
 */
class LoyaltyPointsService
{
    /**
     * Fallback values used whenever the admin hasn't (yet) set the matching
     * Setting row. See `ReferralService`'s equivalent constants for referral
     * program defaults.
     */
    private const DEFAULT_EARN_RATE_BDT_PER_POINT = 150.0;

    private const DEFAULT_EARN_DELAY_DAYS = 7;

    private const DEFAULT_REGISTRATION_BONUS_POINTS = 20;

    private const DEFAULT_REVIEW_BONUS_POINTS = 15;

    private const DEFAULT_DAILY_LOGIN_BASE_POINTS = 2;

    private const DEFAULT_DAILY_LOGIN_CAP_DAYS = 30;

    private const DEFAULT_REDEEM_MAX_PERCENT = 50.0;

    private const DHAKA_TIMEZONE = 'Asia/Dhaka';

    public function __construct(
        private readonly ReferralService $referrals,
        private readonly RefundCalculator $refunds,
    ) {}

    public function pointsValue(): float
    {
        return $this->referrals->pointsValue();
    }

    public function redeemMaxPercent(): float
    {
        return max(0.0, min(100.0, (float) (Setting::find('points_redeem_max_percent')?->value ?? self::DEFAULT_REDEEM_MAX_PERCENT)));
    }

    /**
     * Points a customer can spend right now: their balance minus whatever
     * their unpaid pending checkouts still hold (see
     * PendingCheckout::scopeHoldingPointsFor()).
     *
     * When this decides a redemption, call it on a user locked by the first
     * statement of the transaction. That lock serializes every reservation
     * and debit for this user, and because a locking read does not start
     * InnoDB's REPEATABLE READ snapshot, this plain read then sees everything
     * committed before the lock was granted. A plain read earlier in the
     * transaction would pin an older snapshot and could miss a reservation.
     */
    public function availablePoints(User $user): int
    {
        // BIZ-010: scopeHoldingPointsFor() only bounds how far back to look
        // (plain non-expiry, or expired within its late-payment grace) —
        // an already-expired row only actually still holds its points when
        // hasUnclaimedProofOfPayment() says so, which a single SQL sum
        // can't decide inline. The result set is always tiny (one user's
        // own in-flight checkouts), so filtering in PHP costs nothing.
        $reserved = PendingCheckout::query()
            ->holdingPointsFor($user->id)
            ->get(['id', 'points_reserved', 'expires_at', 'created_at', 'checkout_data'])
            ->filter(fn (PendingCheckout $pending): bool => ! $pending->isExpired() || $pending->hasUnclaimedProofOfPayment())
            ->sum('points_reserved');

        return max(0, $user->points_balance - $reserved);
    }

    /**
     * The most points a customer could apply at checkout right now, capped
     * by both their available points and the configured
     * max-percent-of-subtotal rule.
     * Clamps whatever the client actually requested server-side.
     */
    public function maxRedeemablePoints(User $user, float $subtotal): int
    {
        $pointsValue = $this->pointsValue();

        if ($pointsValue <= 0 || $subtotal <= 0) {
            return 0;
        }

        $maxAmount = $subtotal * ($this->redeemMaxPercent() / 100);
        $maxByPercent = (int) floor($maxAmount / $pointsValue);

        return max(0, min($this->availablePoints($user), $maxByPercent));
    }

    /**
     * Currency value of redeeming N points at today's conversion rate,
     * clamped to the available points and the max-percent-of-subtotal rule. Pure
     * calculation — writes nothing. Call on the LOCKED user so the check
     * reflects any concurrent transaction.
     */
    public function redeemPoints(User $lockedUser, int $points, float $subtotal): float
    {
        $points = min($points, $this->maxRedeemablePoints($lockedUser, $subtotal));

        if ($points <= 0) {
            return 0.0;
        }

        return round($points * $this->pointsValue(), 2);
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

        // BIZ-010: MobilePaymentController's FSA-07 late-payment grace can
        // confirm an order after its PendingCheckout's points hold already
        // expired and released (scopeHoldingPointsFor), letting the same
        // points be legitimately redeemed again elsewhere in the gap.
        // Debiting what's no longer there would drive the balance negative
        // and silently double the discount's cost. The order itself was
        // already priced and paid with the discount applied, so it still
        // completes — only the (now unsafe) debit is skipped, flagged for
        // admin follow-up instead.
        if ($lockedUser->points_balance < $points) {
            Log::warning('[Loyalty] Skipped a points debit that would have gone negative', [
                'order_id' => $order->id,
                'user_id' => $lockedUser->id,
                'requested_points' => $points,
                'current_balance' => $lockedUser->points_balance,
            ]);

            $order->statusHistories()->create([
                'status' => $order->status->value,
                'notes' => "Points redemption (-{$points}) could not be applied: balance was already insufficient when this order was confirmed. Review manually.",
            ]);

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
     * Reverse every points side effect of an order that just transitioned to
     * cancelled/refunded: the referrer's reward (if any — delegated to
     * `ReferralService`, which is the only thing that knows whether/when it
     * was granted) and this order's own redeemed points.
     */
    public function handleOrderCancelledOrRefunded(Order $order): void
    {
        $this->referrals->handleOrderCancelledOrRefunded($order);
        $this->refundRedeemedPointsIfApplicable($order);

        // PAY-RETURN-001: a refund (not a cancellation) also takes back the
        // order-earned points attributable to the refunded money.
        if ($order->status === OrderStatus::REFUNDED) {
            $this->reconcileEarnedPointsAfterRefund($order);
        }
    }

    /**
     * PAY-RETURN-001: reverse order-earned points proportionally to the
     * product money actually refunded since the points were granted — the
     * balance may go negative (approved policy). Idempotent: the target is
     * recomputed from the ledgers and only the difference is reversed.
     *
     *  - Grants recorded with their spend basis S (`currency_amount`): refunds
     *    already reflected in the grant are R0 = N − S; reversal covers R − R0.
     *  - Grants made before PAY-RETURN-001 (no basis): basis B = subtotal −
     *    discount and only refunds recorded after deployment count, so a
     *    refund made before deployment never triggers a reversal on its own.
     */
    public function reconcileEarnedPointsAfterRefund(Order $order): void
    {
        if ($order->user_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = Order::withTrashed()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->user_id === null) {
                return;
            }

            $grants = ReferralPointTransaction::where('order_id', $locked->id)
                ->where('user_id', $locked->user_id)
                ->where('type', PointTransactionType::EARN_ORDER)
                ->lockForUpdate()
                ->get();
            $earned = (int) $grants->sum('points');

            if ($earned <= 0) {
                return;
            }

            $alreadyReversed = -1 * (int) ReferralPointTransaction::where('order_id', $locked->id)
                ->where('user_id', $locked->user_id)
                ->where('type', PointTransactionType::REVOKE_ORDER)
                ->lockForUpdate()
                ->sum('points');

            $refunded = $this->refunds->refundedPaisa($locked, lock: true);
            $cap = $this->refunds->capPaisa($locked) ?? 0;
            $basis = $grants->first()->currency_amount;

            if ($basis !== null) {
                $spend = RefundCalculator::toPaisa($basis);
                $sinceGrant = $refunded - ($this->refunds->productNetPaisa($locked) - $spend);
                $target = ($refunded >= $cap && $cap > 0) || ($spend > 0 && $sinceGrant >= $spend)
                    ? $earned
                    : ($spend > 0 ? intdiv($earned * max(0, $sinceGrant), $spend) : 0);
            } else {
                $legacyBasis = max(0, RefundCalculator::toPaisa($locked->subtotal) - RefundCalculator::toPaisa($locked->discount));
                $sinceGrant = RefundCalculator::toPaisa(OrderRefund::where('order_id', $locked->id)->where('is_backfilled', false)->sum('amount'));
                $hasLegacyRefunds = OrderRefund::where('order_id', $locked->id)->where('is_backfilled', true)->exists();
                $target = ! $hasLegacyRefunds && $refunded >= $cap && $cap > 0
                    ? $earned
                    : ($legacyBasis > 0 ? intdiv($earned * $sinceGrant, $legacyBasis) : 0);
            }

            $delta = min($earned, max(0, $target)) - $alreadyReversed;

            if ($delta <= 0) {
                return;
            }

            $customer = User::whereKey($locked->user_id)->lockForUpdate()->first();

            if ($customer === null) {
                return;
            }

            // Signed column since PAY-RETURN-001: may go below zero; later earnings offset it.
            $customer->decrement('points_balance', $delta);

            ReferralPointTransaction::create([
                'user_id' => $customer->id,
                'order_id' => $locked->id,
                'type' => PointTransactionType::REVOKE_ORDER,
                'points' => -$delta,
                'currency_amount' => RefundCalculator::toTaka(max(0, $sinceGrant)),
                'note' => 'Order '.$locked->order_number.' refund — order-earned points reversed'.($basis === null ? ' (pre-ledger grant basis).' : '.'),
            ]);
        }, 3);
    }

    /**
     * PAY-RETURN-001-BATCH-2: restore redeemed points proportionally to the
     * product money refunded by a *partial* return — the mirror image of
     * `reconcileEarnedPointsAfterRefund()` above, same idempotent
     * recompute-the-target-then-apply-the-delta shape, same lock order.
     *
     * `points_redeemed` is fixed once at checkout and never changes, so
     * (unlike earned points, which can be granted at different times with
     * different spend bases) there is only ever one basis to track: the
     * order's own product-net value. target = points_redeemed × (refunded
     * ÷ productNet), floored; once refunded reaches productNet the target
     * is exactly points_redeemed (integer division of a value by itself),
     * so the final partial return in a sequence always lands on the exact
     * original total — no remainder is ever lost across a split.
     *
     * Deliberately NOT called when a return fully closes the order —
     * `RefundService::recordReturnRefund()` only calls this in its partial
     * branch. A fully-returned order's status flips to REFUNDED, which
     * already triggers the existing, unconditional, marker-based full
     * reversal in `refundRedeemedPointsIfApplicable()` via
     * `Order::booted()` — untouched by this method, so full-order
     * refund/cancellation behavior is unchanged.
     */
    public function reconcileRedeemedPointsAfterRefund(Order $order): void
    {
        if ($order->user_id === null || ! $order->points_redeemed) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = Order::withTrashed()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->user_id === null || ! $locked->points_redeemed) {
                return;
            }

            $redeemed = $locked->points_redeemed;

            $alreadyRestored = (int) ReferralPointTransaction::where('order_id', $locked->id)
                ->where('user_id', $locked->user_id)
                ->where('type', PointTransactionType::REFUND_PARTIAL)
                ->lockForUpdate()
                ->sum('points');

            $productNet = $this->refunds->productNetPaisa($locked);

            if ($productNet <= 0) {
                return;
            }

            $refunded = min($this->refunds->refundedPaisa($locked, lock: true), $productNet);
            $target = intdiv($redeemed * $refunded, $productNet);

            $delta = $target - $alreadyRestored;

            if ($delta <= 0) {
                return;
            }

            $customer = User::whereKey($locked->user_id)->lockForUpdate()->first();

            if ($customer === null) {
                return;
            }

            $customer->increment('points_balance', $delta);

            ReferralPointTransaction::create([
                'user_id' => $customer->id,
                'order_id' => $locked->id,
                'type' => PointTransactionType::REFUND_PARTIAL,
                'points' => $delta,
                'currency_amount' => RefundCalculator::toTaka($refunded),
                'note' => 'Order '.$locked->order_number.' partial return — redeemed points restored proportionally to the refunded amount.',
            ]);
        }, 3);
    }

    /**
     * PROMO-LOY-001: previously locked only the User row with no enclosing
     * transaction, so the lock was released the instant that one statement
     * completed (MySQL autocommit) — and the idempotency check read
     * `$order->points_refunded_at` from the possibly-stale in-memory object
     * the caller passed in, not a fresh row. Two near-simultaneous triggers
     * (e.g. an admin cancelling while a courier sync processes a stale
     * report for the same order) could both pass the check and both credit
     * the refund. Fixed to match the already-correct pattern used by
     * `InventoryService::restoreForOrder()`/`CouponService::release()`:
     * lock the *Order* row first (it owns the idempotency marker) inside a
     * transaction, re-check the marker on that locked row, then lock the
     * user row. Order-before-user lock order is consistent with every
     * other method in this file and in `ReferralService`.
     */
    private function refundRedeemedPointsIfApplicable(Order $order): void
    {
        // Cheap pre-check outside the transaction for the common no-op case.
        // `points_redeemed` is set once at checkout and never changes
        // afterward, so reading it from the (possibly stale) in-memory
        // $order here is safe — only `points_refunded_at` is the mutable
        // idempotency flag, which is re-checked on the locked row below.
        if (! $order->points_redeemed || $order->user_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($lockedOrder === null || ! $lockedOrder->points_redeemed || $lockedOrder->points_refunded_at !== null || $lockedOrder->user_id === null) {
                return;
            }

            $purchaser = User::whereKey($lockedOrder->user_id)->lockForUpdate()->first();

            if ($purchaser === null) {
                return;
            }

            // PAY-RETURN-001-BATCH-2: a sequence of partial returns can land
            // on a fully-returned order (every unit eventually refunded)
            // without ever going through this method — each one already
            // restored its own proportional share via REFUND_PARTIAL. Only
            // the remainder needed to reach points_redeemed exactly is owed
            // here; in the common case (no prior partial restorations) this
            // is the full amount, unchanged from before.
            $alreadyRestored = (int) ReferralPointTransaction::where('order_id', $lockedOrder->id)
                ->where('user_id', $lockedOrder->user_id)
                ->where('type', PointTransactionType::REFUND_PARTIAL)
                ->lockForUpdate()
                ->sum('points');

            $remaining = max(0, $lockedOrder->points_redeemed - $alreadyRestored);

            if ($remaining > 0) {
                $purchaser->increment('points_balance', $remaining);

                ReferralPointTransaction::create([
                    'user_id' => $purchaser->id,
                    'order_id' => $lockedOrder->id,
                    'type' => PointTransactionType::REFUND,
                    'points' => $remaining,
                    'note' => 'Order '.$lockedOrder->order_number.' was cancelled/refunded — redeemed points refunded.',
                ]);
            }

            $lockedOrder->forceFill(['points_refunded_at' => now()])->saveQuietly();
        }, 3);
    }

    /**
     * Award the once-per-account signup bonus. Call right after the user
     * row is created — idempotent via a guard query, so a retried/duplicated
     * call (an event firing twice, say) never double-credits.
     *
     * Runs inside a transaction (retried up to 3 times on deadlock) with
     * the User row locked first: the `exists()` guard query that follows
     * is then the transaction's first *plain* read, so under InnoDB
     * REPEATABLE READ its snapshot is established only after the lock is
     * granted (and, if contended, only after the prior holder committed)
     * — meaning it correctly sees that commit instead of a snapshot
     * frozen before it. Same technique proven in
     * `tests/Feature/CouponConcurrencyRaceTest.php`.
     */
    public function grantRegistrationBonus(User $user): void
    {
        $points = max(0, (int) (Setting::find('registration_bonus_points')?->value ?? self::DEFAULT_REGISTRATION_BONUS_POINTS));

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $points): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $alreadyGranted = ReferralPointTransaction::where('user_id', $locked->id)
                ->where('type', PointTransactionType::EARN_REGISTRATION)
                ->exists();

            if ($alreadyGranted) {
                return;
            }

            $locked->increment('points_balance', $points);

            ReferralPointTransaction::create([
                'user_id' => $locked->id,
                'type' => PointTransactionType::EARN_REGISTRATION,
                'points' => $points,
                'note' => 'New account bonus.',
            ]);
        }, 3);
    }

    /**
     * Award the verified-purchase review bonus, if this review qualifies:
     * approved, and linked to an order that actually delivered the reviewed
     * product to this reviewer. Safe to call on every save (submit,
     * re-approve, admin edit) — only ever grants once per review.
     *
     * Runs inside a transaction (retried up to 3 times on deadlock) that
     * locks the *Review* row first — it owns the idempotency marker and
     * always exists for a given review, unlike a ledger-table row — then
     * re-checks the marker on that locked row before locking the user,
     * mirroring the Order-before-user pattern used everywhere else in
     * this file, with Review playing the same role Order does elsewhere.
     * A bare `lockForUpdate()` outside a transaction wouldn't actually stop
     * two independently-loaded, stale copies of the same review (e.g. an
     * admin double-clicking "Approve") from both granting — the lock only
     * does anything useful while held inside an open transaction.
     */
    public function grantReviewBonus(Review $review): void
    {
        if (! $review->is_approved || $review->order_id === null || $review->points_awarded_at !== null) {
            return;
        }

        $points = max(0, (int) (Setting::find('review_bonus_points')?->value ?? self::DEFAULT_REVIEW_BONUS_POINTS));

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($review, $points): void {
            $lockedReview = Review::whereKey($review->id)->lockForUpdate()->first();

            if ($lockedReview === null
                || ! $lockedReview->is_approved
                || $lockedReview->order_id === null
                || $lockedReview->points_awarded_at !== null) {
                return;
            }

            $user = User::whereKey($lockedReview->user_id)->lockForUpdate()->first();

            if ($user === null) {
                return;
            }

            // BIZ-036: a customer may rewrite a review an admin deleted, but
            // the bonus is once per customer per product.
            $alreadyRewarded = Review::withTrashed()
                ->where('user_id', $lockedReview->user_id)
                ->where('product_id', $lockedReview->product_id)
                ->whereKeyNot($lockedReview->id)
                ->whereNotNull('points_awarded_at')
                ->exists();

            if ($alreadyRewarded) {
                return;
            }

            $user->increment('points_balance', $points);

            ReferralPointTransaction::create([
                'user_id' => $user->id,
                'order_id' => $lockedReview->order_id,
                'type' => PointTransactionType::EARN_REVIEW,
                'points' => $points,
                'note' => 'Verified review bonus.',
            ]);

            $lockedReview->forceFill(['points_awarded_at' => now()])->saveQuietly();
        }, 3);
    }

    public function earnDelayDays(): int
    {
        return max(0, (int) (Setting::find('loyalty_earn_delay_days')?->value ?? self::DEFAULT_EARN_DELAY_DAYS));
    }

    public function earnRateBdtPerPoint(): float
    {
        return max(0.0, (float) (Setting::find('loyalty_earn_rate_bdt_per_point')?->value ?? self::DEFAULT_EARN_RATE_BDT_PER_POINT));
    }

    /**
     * Credit spend-based points on every delivered order that has now sat
     * past the earn delay (covering the return window) without being
     * cancelled/returned. 1 point per `loyalty_earn_rate_bdt_per_point` BDT
     * of the order's subtotal (product spend only — shipping is a separate
     * column and never included), rounded UP so any order earns at least 1
     * point. Intended to run on a schedule; see `rewards:grant-due`.
     *
     * The query above is only a candidate pre-filter — `withoutOverlapping()`
     * on the scheduled command guards against the scheduler itself double-
     * dispatching, but not a manually-run invocation overlapping it, so each
     * order is re-fetched and locked fresh inside its own per-order
     * transaction (retried up to 3 times on deadlock), re-checking both
     * `status === DELIVERED` and `loyalty_points_granted_at` on that locked
     * row before touching the user — Order-before-user, consistent with
     * every other method here. This guards against a concurrent/retried run
     * of the same scheduled command reprocessing an order whose status or
     * marker was only read once, at the top of the batch (e.g. the order
     * was cancelled in the meantime).
     */
    public function grantDueOrderEarnPoints(): int
    {
        $rate = $this->earnRateBdtPerPoint();

        if ($rate <= 0) {
            return 0;
        }

        $cutoff = CarbonImmutable::now(self::DHAKA_TIMEZONE)->subDays($this->earnDelayDays());

        $dueOrderIds = Order::where('status', OrderStatus::DELIVERED)
            ->whereNotNull('delivered_at')
            ->whereNull('loyalty_points_granted_at')
            ->whereNotNull('user_id')
            ->where('delivered_at', '<=', $cutoff)
            ->orderBy('delivered_at')
            ->pluck('id');

        $granted = 0;

        foreach ($dueOrderIds as $orderId) {
            $counted = DB::transaction(function () use ($orderId, $rate): bool {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();

                if ($order === null
                    || $order->status !== OrderStatus::DELIVERED
                    || $order->loyalty_points_granted_at !== null
                    || $order->user_id === null) {
                    return true;
                }

                // PAY-RETURN-001: while a return is pending or approved the
                // grant waits (marker left unset, so a later run retries);
                // a rejected return releases it, a refunded one reduces it.
                if (ReturnRequest::where('order_id', $order->id)->whereIn('status', [ReturnStatus::PENDING, ReturnStatus::APPROVED])->exists()) {
                    return false;
                }

                // PAY-RETURN-001: spend = product net (discount already
                // allocated) minus the product money actually refunded so far
                // (the shared refund ledger). The basis is stored on the
                // ledger row so a later reversal knows exactly what it earned on.
                $spendPaisa = max(0, $this->refunds->productNetPaisa($order) - $this->refunds->refundedPaisa($order, lock: true));
                $spend = RefundCalculator::toTaka($spendPaisa);

                $points = $spend > 0 ? (int) ceil($spend / $rate) : 0;

                if ($points > 0) {
                    $user = User::whereKey($order->user_id)->lockForUpdate()->first();

                    if ($user !== null) {
                        $user->increment('points_balance', $points);

                        ReferralPointTransaction::create([
                            'user_id' => $user->id,
                            'order_id' => $order->id,
                            'type' => PointTransactionType::EARN_ORDER,
                            'points' => $points,
                            'currency_amount' => $spend,
                            'note' => 'Earned from order '.$order->order_number.'.',
                        ]);
                    }
                }

                $order->forceFill(['loyalty_points_granted_at' => now()])->saveQuietly();

                return true;
            }, 3);

            $granted += $counted ? 1 : 0;
        }

        return $granted;
    }

    public function dailyLoginBasePoints(): int
    {
        return max(0, (int) (Setting::find('daily_login_base_points')?->value ?? self::DEFAULT_DAILY_LOGIN_BASE_POINTS));
    }

    public function dailyLoginCapDays(): int
    {
        return max(1, (int) (Setting::find('daily_login_cap_days')?->value ?? self::DEFAULT_DAILY_LOGIN_CAP_DAYS));
    }

    /**
     * Today's streak day (1-indexed) and whether it's already been claimed,
     * without claiming it — for rendering the widget. Streak day N earns
     * N × base points, capped at `dailyLoginCapDays()`.
     *
     * @return array{streakDay: int, claimedToday: bool, points: int}
     */
    public function dailyLoginStatus(User $user): array
    {
        $today = CarbonImmutable::now(self::DHAKA_TIMEZONE)->toDateString();
        $lastDate = $user->login_streak_date?->toDateString();
        $claimedToday = $lastDate === $today;
        $streakDay = $claimedToday ? max(1, $user->login_streak_count) : $this->nextStreakDay($user, $today);

        return [
            'streakDay' => $streakDay,
            'claimedToday' => $claimedToday,
            'points' => $this->pointsForStreakDay($streakDay),
        ];
    }

    /**
     * Claim today's daily-login bonus. Idempotent per Asia/Dhaka calendar
     * day regardless of how many times/devices it's called from — a second
     * call on the same day is a no-op that reports `claimedToday: true`
     * rather than erroring, since the frontend's own disabled-button state
     * can't fully prevent a double-submit.
     *
     * Wrapped in a transaction (retried up to 3 times on deadlock) around
     * the existing user-row lock and locked-instance date check, which were
     * already correct — `lockForUpdate()` only actually serializes two
     * near-simultaneous claims (e.g. a double-submit from two tabs) while
     * held inside an open transaction; calling it standalone just acquires
     * and releases the row lock within MySQL's own implicit per-statement
     * autocommit, which can't block a second, genuinely concurrent caller.
     *
     * @return array{streakDay: int, claimedToday: bool, points: int, awarded: bool}
     */
    public function claimDailyLogin(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($locked === null) {
                return ['streakDay' => 1, 'claimedToday' => false, 'points' => 0, 'awarded' => false];
            }

            $today = CarbonImmutable::now(self::DHAKA_TIMEZONE)->toDateString();

            if ($locked->login_streak_date?->toDateString() === $today) {
                return [
                    'streakDay' => max(1, $locked->login_streak_count),
                    'claimedToday' => true,
                    'points' => $this->pointsForStreakDay(max(1, $locked->login_streak_count)),
                    'awarded' => false,
                ];
            }

            $streakDay = $this->nextStreakDay($locked, $today);
            $points = $this->pointsForStreakDay($streakDay);

            $locked->forceFill([
                'login_streak_count' => $streakDay,
                'login_streak_date' => $today,
            ])->save();

            if ($points > 0) {
                $locked->increment('points_balance', $points);

                ReferralPointTransaction::create([
                    'user_id' => $locked->id,
                    'type' => PointTransactionType::EARN_DAILY_LOGIN,
                    'points' => $points,
                    'note' => "Daily login bonus — day {$streakDay}.",
                ]);
            }

            return ['streakDay' => $streakDay, 'claimedToday' => true, 'points' => $points, 'awarded' => true];
        }, 3);
    }

    /**
     * Day the NEXT claim would land on: +1 if the last claim was yesterday
     * (Asia/Dhaka), 1 if it was never claimed or the streak was broken (a
     * day missed resets it), unaffected by an already-claimed today (callers
     * needing that case check it themselves first).
     */
    private function nextStreakDay(User $user, string $today): int
    {
        $lastDate = $user->login_streak_date?->toDateString();
        $yesterday = CarbonImmutable::parse($today, self::DHAKA_TIMEZONE)->subDay()->toDateString();

        return $lastDate === $yesterday ? $user->login_streak_count + 1 : 1;
    }

    private function pointsForStreakDay(int $streakDay): int
    {
        return min($streakDay, $this->dailyLoginCapDays()) * $this->dailyLoginBasePoints();
    }
}
