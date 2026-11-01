<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItem;
use App\Models\HomepageSetting;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * MED-10: the shipping-cost rule shared by the main cart checkout, the
 * landing-page single-product checkout, and admin manual order pricing —
 * previously three independently-maintained copies (SHIP-DUP-001 fixed one
 * real divergence between them, not the duplication itself). All three
 * share the exact same three steps: a subtotal-based free-delivery
 * threshold, then per-item free delivery (product flag + optional minimum
 * quantity), then the admin-configured location rate as a fallback.
 *
 * The one genuine behavioral difference between contexts is bundles: only
 * the main cart checkout can contain a bundle, so only it may also award
 * free delivery through BundleService::itemQualifiesForFreeDelivery(). That
 * difference is expressed as two distinctly-named public methods rather
 * than a flag, so a landing-page or manual-order call site cannot
 * accidentally gain bundle behavior by passing the wrong boolean.
 */
class ShippingCostCalculator
{
    public function __construct(private readonly BundleService $bundles) {}

    /**
     * Main cart checkout only. A cart item can earn free delivery either
     * through an intact free-delivery bundle or through its own product's
     * free_delivery/free_delivery_min_qty — mirrors the original
     * CheckoutController::calculateShippingCost() exactly.
     *
     * @param  array{state?: string|null, city?: string|null}  $shippingAddress
     * @param  Collection<int, CartItem>  $cartItems
     */
    public function forCartWithBundles(array $shippingAddress, Collection $cartItems, float $subtotal): float
    {
        $eligibility = $cartItems->map(fn (CartItem $item): bool => $this->bundles->itemQualifiesForFreeDelivery($item)
            || $this->productQualifies($item->product->free_delivery, $item->product->free_delivery_min_qty, $item->quantity));

        return $this->cost($shippingAddress, $subtotal, $eligibility);
    }

    /**
     * Any context with no concept of bundles — the landing-page single-
     * product checkout and admin manual order pricing. Free delivery is
     * decided purely by each item's own free_delivery/free_delivery_min_qty;
     * there is no bundle_id to check in either caller, so there is nothing
     * for this method to accept that would let bundle logic leak in.
     *
     * @param  array{state?: string|null, city?: string|null}  $shippingAddress
     * @param  array<int, array{free_delivery: bool, free_delivery_min_qty: int|null, quantity: int}>  $items
     */
    public function forItemsWithoutBundles(array $shippingAddress, array $items, float $subtotal): float
    {
        $eligibility = collect($items)->map(fn (array $item): bool => $this->productQualifies(
            $item['free_delivery'],
            $item['free_delivery_min_qty'],
            $item['quantity'],
        ));

        return $this->cost($shippingAddress, $subtotal, $eligibility);
    }

    /**
     * @param  array{state?: string|null, city?: string|null}  $shippingAddress
     * @param  Collection<int, bool>  $itemEligibility  Whether each item, independently, already qualifies for free delivery.
     */
    private function cost(array $shippingAddress, float $subtotal, Collection $itemEligibility): float
    {
        $threshold = $this->freeDeliveryThreshold();

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        // An empty item list falls through to the standard rate, exactly as
        // before — it was never treated as "everything qualifies".
        if ($itemEligibility->isNotEmpty() && $itemEligibility->every(fn (bool $eligible): bool => $eligible)) {
            return 0.0;
        }

        return Setting::shippingRateFor($shippingAddress['state'] ?? null, $shippingAddress['city'] ?? null);
    }

    private function productQualifies(bool $freeDelivery, ?int $minQuantity, int $quantity): bool
    {
        if (! $freeDelivery) {
            return false;
        }

        return $minQuantity === null || $quantity >= $minQuantity;
    }

    private function freeDeliveryThreshold(): float
    {
        $bar = HomepageSetting::where('section_key', 'announcement_bar')->first();

        return (float) ($bar?->metadata['free_delivery_amount'] ?? 0);
    }
}
