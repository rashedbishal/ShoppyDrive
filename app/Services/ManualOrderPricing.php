<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Support\CartManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * What a manual admin order would cost under the normal rules, computed only
 * from the server's own catalog, so staff can deviate from it but never
 * misstate it. Manual orders carry no bundle_id and skip coupon, referral and
 * loyalty logic (see Admin\OrderController::store()), so an item is priced
 * standalone and the calculated discount is always zero.
 */
class ManualOrderPricing
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly ShippingCostCalculator $shippingCalculator,
    ) {}

    /**
     * @param  array<int, array{product_id: int|string, variant_id?: int|string|null, quantity: int|string}>  $items
     * @return array{prices: array<int, float>, subtotal: float, shipping_cost: float, discount: float}
     */
    public function calculate(array $items, ?string $state, ?string $city): array
    {
        $products = Product::query()->whereKey(array_column($items, 'product_id'))->get()->keyBy('id');
        $prices = [];
        $lines = [];

        foreach ($items as $index => $item) {
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            $price = $this->cartManager->resolvePrice((int) $item['product_id'], $variantId);
            $product = $products->get($item['product_id']);

            $prices[$index] = $price;
            $lines[] = [
                'quantity' => (int) $item['quantity'],
                'subtotal' => $price * (int) $item['quantity'],
                'free_delivery' => (bool) $product?->free_delivery,
                'free_delivery_min_qty' => $product?->free_delivery_min_qty,
            ];
        }

        $subtotal = (float) array_sum(array_column($lines, 'subtotal'));

        return [
            'prices' => $prices,
            'subtotal' => $subtotal,
            'shipping_cost' => $this->shippingCost(['state' => $state, 'city' => $city], collect($lines), $subtotal),
            'discount' => 0.0,
        ];
    }

    /**
     * MED-10: delegates to the shared ShippingCostCalculator. Bundle
     * eligibility (its other free-delivery path, via
     * BundleService::itemQualifiesForFreeDelivery()) does not apply to
     * admin-entered orders: neither request collects a bundle_id, so they
     * can never be a bundle in the first place — this is exactly why
     * forItemsWithoutBundles(), not forCartWithBundles(), is used here.
     *
     * @param  array<string, mixed>  $shippingAddress
     * @param  Collection<int, array{quantity: int, free_delivery: bool, free_delivery_min_qty: ?int}>  $items
     */
    public function shippingCost(array $shippingAddress, Collection $items, float $subtotal): float
    {
        return $this->shippingCalculator->forItemsWithoutBundles($shippingAddress, $items->all(), $subtotal);
    }

    /** Compared in poisha, so float noise never reads as a staff override. */
    public static function differs(float $entered, float $calculated): bool
    {
        return (int) round($entered * 100) !== (int) round($calculated * 100);
    }

    /**
     * Records every deviation from the calculated pricing on this order, or
     * nothing when there is none. Call inside the order's transaction so a
     * rolled-back order leaves no entry behind.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array{prices: array<int, float>, subtotal: float, shipping_cost: float, discount: float}  $calculated
     * @param  array{shipping_cost: float, discount: float, shipping_reason: ?string, discount_reason: ?string}  $entered
     */
    public function logOverrides(Order $order, array $items, array $calculated, array $entered): void
    {
        $itemOverrides = [];

        foreach ($items as $index => $item) {
            if (self::differs((float) $item['price'], $calculated['prices'][$index])) {
                $itemOverrides[] = [
                    'product_id' => (int) $item['product_id'],
                    'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                    'quantity' => (int) $item['quantity'],
                    'calculated_price' => $calculated['prices'][$index],
                    'final_price' => (float) $item['price'],
                    'reason' => $item['price_override_reason'],
                ];
            }
        }

        $shippingOverridden = self::differs($entered['shipping_cost'], $calculated['shipping_cost']);
        $discountOverridden = self::differs($entered['discount'], $calculated['discount']);

        if ($itemOverrides === [] && ! $shippingOverridden && ! $discountOverridden) {
            return;
        }

        $overridden = array_filter([
            $itemOverrides !== [] ? 'item prices' : null,
            $shippingOverridden ? 'shipping' : null,
            $discountOverridden ? 'discount' : null,
        ]);

        activity('orders')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($order)
            ->event('pricing_override')
            ->withProperties([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'items' => $itemOverrides,
                'shipping' => [
                    'calculated' => $calculated['shipping_cost'],
                    'final' => $entered['shipping_cost'],
                    'reason' => $shippingOverridden ? $entered['shipping_reason'] : null,
                ],
                'discount' => [
                    'calculated' => $calculated['discount'],
                    'final' => $entered['discount'],
                    'reason' => $discountOverridden ? $entered['discount_reason'] : null,
                ],
            ])
            ->log("Order {$order->order_number} created with manual pricing: ".implode(', ', $overridden).'.');
    }
}
