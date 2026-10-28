<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'float',
            'min_order_amount' => 'float',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function auditLabel(): string
    {
        return "{$this->code} ({$this->name})";
    }

    /**
     * `used_count` ticks up on every order that redeems this coupon — a
     * checkout action, not an admin edit — so a solo bump shouldn't appear
     * as an "admin" audit entry.
     */
    protected function auditIgnoreWhenOnlyChanged(): array
    {
        return ['used_count'];
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CouponUse::class);
    }

    /**
     * The single source of truth for "can this coupon be applied right
     * now" — used identically by the /coupon/validate preview endpoint
     * and the authoritative check in CheckoutController::resolveCoupon(),
     * so the two can never disagree about eligibility. Returns a
     * human-readable error, or null if eligible. Does NOT check
     * max_uses/per_user_limit under a lock — that race-sensitive,
     * authoritative re-check happens separately in CouponService::consume()
     * at the moment of actual order creation, mirroring how flash-sale
     * stock caps are only authoritatively enforced at commit time.
     */
    public function eligibilityError(float $subtotal, ?int $userId, ?string $guestPhone = null): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is not active.';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not yet active.';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }

        if ($this->min_order_amount !== null && $subtotal < $this->min_order_amount) {
            return 'Minimum order amount of ৳'.number_format($this->min_order_amount, 0).' required.';
        }

        if ($userId !== null && $this->uses()->where('user_id', $userId)->count() >= $this->per_user_limit) {
            return 'You have already used this coupon the maximum number of times.';
        }

        // PROMO-001: guests have no account to key per_user_limit against,
        // so per_user_limit was previously unenforceable for them at all —
        // see guestRedemptionCount()'s docblock for how this is derived.
        if ($userId === null && $guestPhone !== null && self::guestRedemptionCount($this->id, $guestPhone) >= $this->per_user_limit) {
            return 'You have already used this coupon the maximum number of times.';
        }

        return null;
    }

    /**
     * Guest checkouts never create a CouponUse row (its user_id column is
     * NOT NULL — there's no account to attach it to), so a guest's prior
     * redemptions of this coupon are derived from completed Order rows
     * instead: same coupon_id, no user_id, and the same phone number
     * already stored on Order.shipping_address at checkout. An order
     * whose coupon was released (cancellation/refund — Order's own
     * coupon_released_at, set by CouponService::release()) is excluded,
     * mirroring how a logged-in customer's CouponUse row is deleted on
     * the same event — both paths agree on what still counts as "used."
     *
     * $excludeOrderId exists because every consume() call site creates
     * the Order row (with coupon_id already set) *before* calling
     * consume() — without excluding it, the order being created would
     * count itself and reject the very first legitimate use.
     */
    public static function guestRedemptionCount(int $couponId, string $guestPhone, ?int $excludeOrderId = null, bool $lockForUpdate = false): int
    {
        return Order::where('coupon_id', $couponId)
            ->whereNull('user_id')
            ->whereNull('coupon_released_at')
            ->where('shipping_address->phone', $guestPhone)
            ->when($excludeOrderId !== null, fn ($q) => $q->where('id', '!=', $excludeOrderId))
            ->when($lockForUpdate, fn ($q) => $q->lockForUpdate())
            ->count();
    }
}
