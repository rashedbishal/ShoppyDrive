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

    /**
     * Lightweight recipe data for every active, multi-item bundle — shared
     * globally with the client so it can recognize the same "these plain
     * cart items happen to add up to a bundle" situation instantly, without
     * waiting on a server round trip. Each component's `base_price` mirrors
     * `basePriceFor()` so the client's own proration math agrees with
     * `unitPriceFor()` here.
     *
     * @return list<array{id:int, name:string, discount_type:string, bundle_price:float|null, items:list<array{product_id:int, variant_id:int|null, quantity:int, base_price:float}>}>
     */
    public function activeRecipes(): array
    {
        return ProductBundle::query()
            ->active()
            ->with(['items.product', 'items.variant'])
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductBundle $bundle): bool => $bundle->items->count() >= 2)
            ->map(fn (ProductBundle $bundle): array => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'discount_type' => $bundle->discount_type->value,
                'bundle_price' => $bundle->bundle_price,
                'items' => $bundle->items->map(fn (ProductBundleItem $item): array => [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'base_price' => (float) ($item->variant?->price ?? $item->product?->price ?? 0),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The inverse of `reconcile()`: looks for active bundles whose full
     * component recipe is already covered by this cart's plain
     * (`bundle_id === null`) items — e.g. the customer added product A and
     * product C to their cart separately, and together they happen to be
     * exactly bundle "A + C" — and retags/splits those items into a proper
     * bundle group at the bundle's price. Must run after `reconcile()` so it
     * only ever considers items that aren't already part of an intact group.
     *
     * Bundles are tried lowest-id-first, so when two active bundles could
     * both claim the same items, the older bundle wins. Mutates the cart's
     * items in place (including adding/removing `CartItem` rows), and
     * refreshes `$cart`'s `items` relation so callers see the final state
     * without needing to refetch.
     *
     * Returns true if any bundle was newly formed/extended this call — a
     * checkout-time caller should treat that exactly like any other price
     * drift (a flash sale starting, a broken bundle reverting) and bounce
     * the customer back to review the corrected total rather than silently
     * charging a different amount than the checkout page just showed.
     */
    public function autoAssemble(Cart $cart): bool
    {
        $items = $cart->items->values();
        $candidates = $items->filter(
            fn (CartItem $item): bool => $item->bundle_id === null && $item->product !== null
        );

        if ($candidates->isEmpty()) {
            return false;
        }

        $bundles = ProductBundle::query()
            ->active()
            ->with('items')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductBundle $bundle): bool => $bundle->items->count() >= 2);

        $assembled = false;

        foreach ($bundles as $bundle) {
            $matches = [];
            $multiplier = null;

            foreach ($bundle->items as $component) {
                if ($component->quantity <= 0) {
                    $multiplier = 0;

                    break;
                }

                $candidate = $candidates->first(
                    fn (CartItem $item): bool => $item->product_id === $component->product_id
                        && $item->variant_id === $component->variant_id
                        && $item->quantity >= $component->quantity
                );

                if ($candidate === null) {
                    $multiplier = 0;

                    break;
                }

                $itemMultiplier = intdiv($candidate->quantity, $component->quantity);
                $multiplier = $multiplier === null ? $itemMultiplier : min($multiplier, $itemMultiplier);
                $matches[] = [$component, $candidate];
            }

            if ($multiplier === null || $multiplier < 1) {
                continue;
            }

            foreach ($matches as [$component, $candidate]) {
                $required = $component->quantity * $multiplier;
                $price = $this->unitPriceFor($bundle->id, $candidate->product_id, $candidate->variant_id)
                    ?? (float) $candidate->price;

                // A separate CartItem row might already represent this exact
                // component for this exact bundle (e.g. added explicitly via
                // "Add bundle to cart" earlier) — merge into it rather than
                // creating a second row for the same (product, variant,
                // bundle) triple, which `groupIsIntact()` would then see as
                // an invalid extra item and tear apart on the next pass.
                $existingBundleRow = $items->first(
                    fn (CartItem $item): bool => $item->bundle_id === $bundle->id
                        && $item->product_id === $candidate->product_id
                        && $item->variant_id === $candidate->variant_id
                );

                $convertedInPlace = false;

                if ($existingBundleRow !== null) {
                    $existingBundleRow->update(['quantity' => $existingBundleRow->quantity + $required]);
                } elseif ($candidate->quantity === $required) {
                    $candidate->update([
                        'bundle_id' => $bundle->id,
                        'bundle_unit_quantity' => $component->quantity,
                        'price' => $price,
                    ]);
                    $convertedInPlace = true;
                } else {
                    $newRow = $cart->items()->create([
                        'product_id' => $candidate->product_id,
                        'variant_id' => $candidate->variant_id,
                        'bundle_id' => $bundle->id,
                        'bundle_unit_quantity' => $component->quantity,
                        'quantity' => $required,
                        'price' => $price,
                    ]);
                    $newRow->setRelation('product', $candidate->product);
                    $newRow->setRelation('variant', $candidate->variant);
                    $items->push($newRow);
                }

                if (! $convertedInPlace) {
                    $remaining = $candidate->quantity - $required;

                    if ($remaining <= 0) {
                        $candidate->delete();
                        $items = $items->reject(fn (CartItem $item): bool => $item->is($candidate))->values();
                        $candidates = $candidates->reject(fn (CartItem $item): bool => $item->is($candidate))->values();
                    } else {
                        $candidate->update(['quantity' => $remaining]);
                    }
                } else {
                    $candidates = $candidates->reject(fn (CartItem $item): bool => $item->is($candidate))->values();
                }
            }

            $assembled = true;
        }

        $cart->setRelation('items', $items->values());

        return $assembled;
    }
}
