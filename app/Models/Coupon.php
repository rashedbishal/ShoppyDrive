<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CouponAppliesTo;
use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Coupon extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const NOT_APPLICABLE_MESSAGE = "This coupon doesn't apply to the items in your cart.";

    public const UNAVAILABLE_MESSAGE = "This coupon isn't available.";

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'applies_to',
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
            'applies_to' => CouponAppliesTo::class,
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

    /**
     * DB-001 (L2): deleting a coupon is a soft delete; one that was ever used
     * can never be permanently deleted (coupon_uses.coupon_id is RESTRICT too).
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (self $coupon): void {
            if ($coupon->uses()->exists() || Order::withTrashed()->where('coupon_id', $coupon->id)->exists()) {
                throw new RuntimeException("Coupon {$coupon->code} has usage history and cannot be permanently deleted.");
            }
        });
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CouponUse::class);
    }

    public function productTargets(): HasMany
    {
        return $this->hasMany(CouponProductTarget::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function subCategories(): BelongsToMany
    {
        return $this->belongsToMany(SubCategory::class, 'coupon_sub_category');
    }

    public function appliesToEverything(): bool
    {
        return $this->applies_to !== CouponAppliesTo::SELECTED;
    }

    /**
     * Whether this coupon covers a cart line. A line qualifies by matching any
     * one target, so a product both listed and in a listed category still gets
     * one discount.
     */
    public function appliesToProduct(Product $product, ?int $variantId): bool
    {
        if ($this->appliesToEverything()) {
            return true;
        }

        return $this->categories->contains('id', $product->category_id)
            || $this->subCategories->contains('id', $product->sub_category_id)
            || $this->productTargets->contains(fn (CouponProductTarget $target): bool => (int) $target->product_id === $product->id
                && ($target->variant_id === null || (int) $target->variant_id === $variantId));
    }

    /**
     * The lines this coupon covers, out of a cart's or checkout's lines.
     *
     * @param  array<int, array{product_id: int, variant_id: int|null, subtotal: float}>  $lines
     * @return array<int, array{product_id: int, variant_id: int|null, subtotal: float}>
     */
    public function eligibleLines(array $lines): array
    {
        if ($this->appliesToEverything()) {
            return $lines;
        }

        $products = Product::whereIn('id', array_column($lines, 'product_id'))->get(['id', 'category_id', 'sub_category_id'])->keyBy('id');

        return array_values(array_filter($lines, function (array $line) use ($products): bool {
            $product = $products->get($line['product_id']);

            return $product !== null && $this->appliesToProduct($product, $line['variant_id']);
        }));
    }

    /**
     * The amount this coupon's discount and minimum order are measured against.
     */
    public function discountFor(float $eligibleSubtotal): float
    {
        return round($this->type->value === 'percent'
            ? min($eligibleSubtotal, $eligibleSubtotal * $this->value / 100)
            : min($eligibleSubtotal, $this->value), 2);
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
    /**
     * SEC-012: the only coupon fields the public preview returns; the cart
     * and checkout pages need nothing else. The whole record would reveal
     * usage counts, limits and dates to anyone holding a code.
     *
     * @param  list<string>|null  $eligibleKeys  the lines a selected-items coupon covers
     * @return array{id: int, code: string, type: mixed, value: mixed, eligible_keys?: list<string>}
     */
    public function publicPreview(?array $eligibleKeys = null): array
    {
        $preview = [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
        ];

        return $eligibleKeys === null ? $preview : [...$preview, 'eligible_keys' => $eligibleKeys];
    }

    public function eligibilityError(float $subtotal, ?int $userId, ?string $guestPhone = null): ?string
    {
        // SEC-012: one answer for all four, so an anonymous check can't learn
        // about an unannounced sale or how far a campaign has run.
        if (! $this->is_active
            || ($this->starts_at && $this->starts_at->isFuture())
            || ($this->expires_at && $this->expires_at->isPast())
            || ($this->max_uses !== null && $this->used_count >= $this->max_uses)) {
            return self::UNAVAILABLE_MESSAGE;
        }

        if ($this->min_order_amount !== null && $subtotal < $this->min_order_amount) {
            return 'Minimum order amount of ৳'.number_format($this->min_order_amount, 0).' required.';
        }

        // BIZ-015: one count for the account and the phone together, so
        // switching between account and guest (or between accounts) with
        // the same phone no longer resets the limit. See redemptionCount().
        if ($this->per_user_limit !== null && self::redemptionCount($this->id, $userId, $guestPhone) >= $this->per_user_limit) {
            return 'You have already used this coupon the maximum number of times.';
        }

        return null;
    }

    /**
     * BIZ-015: how many times this customer has used the coupon, counting
     * each un-released order once if EITHER it is one of the account's
     * own redemptions (a CouponUse row) OR its delivery phone is this
     * phone, whether it was placed as a guest or from any account. Before,
     * account uses and guest-by-phone uses were counted separately, so a
     * limit-1 coupon could be used once logged in and again as a guest (or
     * from a second account) with the same phone. A different phone still
     * can't be tied to the same person without verifying it.
     * Cancellation/refund releases the coupon on the order
     * (coupon_released_at) and deletes its CouponUse, so it stops counting.
     * Guest checkouts create no CouponUse (user_id is NOT NULL), which is
     * why phone matching reads the order's shipping_address.
     *
     * $excludeOrderId: every consume() call site creates the Order (with
     * coupon_id set) before calling consume(), so without it the order
     * being created would count itself and reject the first legitimate use.
     */
    public static function redemptionCount(int $couponId, ?int $userId, ?string $phone, ?int $excludeOrderId = null, bool $lockForUpdate = false): int
    {
        $phone = $phone !== null && $phone !== '' ? $phone : null;

        if ($userId === null && $phone === null) {
            return 0;
        }

        return Order::where('coupon_id', $couponId)
            ->whereNull('coupon_released_at')
            ->where(function ($query) use ($couponId, $userId, $phone): void {
                if ($userId !== null) {
                    $query->whereIn('id', CouponUse::select('order_id')->where('coupon_id', $couponId)->where('user_id', $userId));
                }

                if ($phone !== null) {
                    $query->orWhere('shipping_address->phone', $phone);
                }
            })
            ->when($excludeOrderId !== null, fn ($q) => $q->where('id', '!=', $excludeOrderId))
            ->when($lockForUpdate, fn ($q) => $q->lockForUpdate())
            ->count();
    }
}
