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

        if ($this->per_user_limit !== null && $userId !== null && $this->uses()->where('user_id', $userId)->count() >= $this->per_user_limit) {
            return 'You have already used this coupon the maximum number of times.';
        }

        // PROMO-001: guests have no account to key per_user_limit against,
        // so per_user_limit was previously unenforceable for them at all —
        // see guestRedemptionCount()'s docblock for how this is derived.
        if ($this->per_user_limit !== null && $userId === null && $guestPhone !== null && self::guestRedemptionCount($this->id, $guestPhone) >= $this->per_user_limit) {
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
