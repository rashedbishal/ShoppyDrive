<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BundleDiscountType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class BundleService
{
    /**
     * The per-unit price a cart item should be charged when it's part of an
     * active, fixed-price bundle — computed fresh from current catalog
     * prices every call (never persisted as a static number), so it tracks
     * live price edits the same way flash-sale pricing already does. A
     * free-delivery bundle has no price override, so this only ever returns
     * non-null for `BundleDiscountType::FixedPrice`.
     *
     * Returns null whenever the bundle can't actually apply (missing,
     * inactive, wrong discount type, or the product/variant isn't one of
     * its components) so the caller can safely fall back to normal pricing.
     */
    public function unitPriceFor(int $bundleId, int $productId, ?int $variantId): ?float
    {
        $bundle = ProductBundle::query()->with('items')->find($bundleId);

        if ($bundle === null || ! $bundle->is_active || $bundle->discount_type !== BundleDiscountType::FixedPrice) {
            return null;
        }

        $component = $bundle->items->first(
            fn (ProductBundleItem $item): bool => $item->product_id === $productId && $item->variant_id === $variantId
        );

        if ($component === null) {
            return null;
        }

        $referenceTotal = 0.0;
        $componentBasePrice = null;

        foreach ($bundle->items as $item) {
            $basePrice = $this->basePriceFor($item->product_id, $item->variant_id);
            $referenceTotal += $basePrice * $item->quantity;

            if ($item->is($component)) {
                $componentBasePrice = $basePrice;
            }
        }

        if ($componentBasePrice === null || $referenceTotal <= 0.0) {
            return null;
        }

        return round(((float) $bundle->bundle_price) * $componentBasePrice / $referenceTotal, 2);
    }

    /**
     * A component's regular catalog price, ignoring any active flash sale —
     * bundle proration always starts from undiscounted prices so the bundle
     * price is the sole discount in effect for its components.
     */
    private function basePriceFor(int $productId, ?int $variantId): float
    {
        if ($variantId !== null) {
            $variant = ProductVariant::query()->find($variantId);

            if ($variant !== null && $variant->price !== null) {
                return (float) $variant->price;
            }
        }

        $product = Product::query()->find($productId);

        return $product !== null ? (float) $product->price : 0.0;
    }

    /**
     * Groups the cart's bundle-tagged items by bundle, and clears `bundle_id`
     * on any item whose group is no longer intact — missing a component,
     * quantities that don't scale to one consistent multiplier, or the
     * bundle itself no longer active. Must run before any bundle-aware
     * pricing/shipping decision is made, so a broken group can never charge
     * or ship as if it were still a real bundle (mirrors how an expired
     * flash sale simply stops applying). Mutates the cart's already-loaded
     * items in place, so callers don't need to refetch afterward.
     */
    public function reconcile(Cart $cart): void
    {
        $itemsByBundle = $cart->items
            ->filter(fn (CartItem $item): bool => $item->bundle_id !== null && $item->product !== null)
            ->groupBy('bundle_id');

        if ($itemsByBundle->isEmpty()) {
            return;
        }

        $bundles = ProductBundle::query()
            ->with('items')
            ->whereIn('id', $itemsByBundle->keys())
            ->get()
            ->keyBy('id');

        foreach ($itemsByBundle as $bundleId => $items) {
            $bundle = $bundles->get((int) $bundleId);

            if ($bundle !== null && $bundle->is_active && $this->groupIsIntact($bundle, $items)) {
                continue;
            }

            foreach ($items as $item) {
                $item->update(['bundle_id' => null, 'bundle_unit_quantity' => null]);
            }
        }
    }

    /**
     * True if `$items` covers every required component of `$bundle` at
     * exactly `component.quantity * M` for one consistent integer `M`, with
     * no extra/unknown items tagged under this bundle.
     */
    private function groupIsIntact(ProductBundle $bundle, Collection $items): bool
    {
        if ($items->count() !== $bundle->items->count()) {
            return false;
        }

        $multiplier = null;

        foreach ($bundle->items as $component) {
            $matching = $items->first(
                fn (CartItem $item): bool => $item->product_id === $component->product_id && $item->variant_id === $component->variant_id
            );

            if ($matching === null || $component->quantity <= 0 || $matching->quantity % $component->quantity !== 0) {
                return false;
            }

            $itemMultiplier = intdiv($matching->quantity, $component->quantity);

            if ($itemMultiplier < 1) {
                return false;
            }

            if ($multiplier === null) {
                $multiplier = $itemMultiplier;
            } elseif ($multiplier !== $itemMultiplier) {
                return false;
            }
        }

        return true;
    }

    /**
     * True only once `reconcile()` has run on the cart this item belongs to
     * — `bundle_id` is only ever non-null for an intact, active group by
     * that point, so this is a cheap "is this specific bundle free-delivery"
     * check, not a re-validation of the whole group.
     */
    public function itemQualifiesForFreeDelivery(CartItem $item): bool
    {
        if ($item->bundle_id === null) {
            return false;
        }

        $bundle = $item->bundle ?? ProductBundle::query()->find($item->bundle_id);

        return $bundle !== null && $bundle->is_active && $bundle->discount_type === BundleDiscountType::FreeDelivery;
    }
}
