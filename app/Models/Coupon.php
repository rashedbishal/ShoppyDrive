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
    public function eligibilityError(float $subtotal, ?int $userId): ?string
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

        return null;
    }
}
