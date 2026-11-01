<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\Setting;
use App\Services\ShippingCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create(['key' => 'shipping_rate_inside_dhaka', 'value' => 75, 'group' => 'shipping']);
    Setting::create(['key' => 'shipping_rate_outside_dhaka', 'value' => 140, 'group' => 'shipping']);
});

function cartItemFor(Product $product, int $quantity = 1, ?int $bundleId = null): CartItem
{
    $cart = Cart::create(['session_id' => 'shipping-calculator-test-'.uniqid()]);

    return $cart->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'bundle_id' => $bundleId,
        'quantity' => $quantity,
        'price' => $product->price,
    ]);
}

test('the free-delivery threshold overrides everything once the subtotal reaches it', function () {
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 1000]]);
    $product = Product::factory()->create(['free_delivery' => false]);
    $item = cartItemFor($product);

    $cost = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$item]),
        1000.0,
    );

    expect($cost)->toBe(0.0);
});

test('below the threshold, a non-qualifying item falls back to the location rate', function () {
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 1000]]);
    $product = Product::factory()->create(['free_delivery' => false]);
    $item = cartItemFor($product);

    $cost = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$item]),
        500.0,
    );

    expect($cost)->toBe(75.0);
});

test('a product flagged free_delivery with no minimum quantity always qualifies', function () {
    $product = Product::factory()->create(['free_delivery' => true, 'free_delivery_min_qty' => null]);
    $item = cartItemFor($product, quantity: 1);

    $cost = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$item]),
        100.0,
    );

    expect($cost)->toBe(0.0);
});

test('the minimum quantity boundary is inclusive, one below it is not', function () {
    $product = Product::factory()->create(['free_delivery' => true, 'free_delivery_min_qty' => 3]);

    $atBoundary = cartItemFor($product, quantity: 3);
    $belowBoundary = cartItemFor($product, quantity: 2);

    $costAtBoundary = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$atBoundary]),
        100.0,
    );
    $costBelowBoundary = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$belowBoundary]),
        100.0,
    );

    expect($costAtBoundary)->toBe(0.0);
    expect($costBelowBoundary)->toBe(75.0);
});

test('a bundle-qualified item gets free delivery in the checkout context even though its product does not', function () {
    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FreeDelivery, 'bundle_price' => null, 'is_active' => true]);
    $product = Product::factory()->create(['free_delivery' => false]);
    $item = cartItemFor($product, quantity: 1, bundleId: $bundle->id);

    $cost = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect([$item]),
        100.0,
    );

    expect($cost)->toBe(0.0);
});

test('bundle eligibility is structurally absent from the no-bundle context, even for the same product', function () {
    // Same product.free_delivery=false as the bundle test above, but routed
    // through forItemsWithoutBundles() — there is no bundle_id parameter to
    // pass here at all, so this cannot accidentally qualify.
    $product = Product::factory()->create(['free_delivery' => false]);

    $cost = app(ShippingCostCalculator::class)->forItemsWithoutBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        [['free_delivery' => (bool) $product->free_delivery, 'free_delivery_min_qty' => $product->free_delivery_min_qty, 'quantity' => 1]],
        100.0,
    );

    expect($cost)->toBe(75.0);
});

test('the location rate correctly distinguishes inside and outside Dhaka', function () {
    $insideCost = app(ShippingCostCalculator::class)->forItemsWithoutBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        [['free_delivery' => false, 'free_delivery_min_qty' => null, 'quantity' => 1]],
        0.0,
    );
    $outsideCost = app(ShippingCostCalculator::class)->forItemsWithoutBundles(
        ['state' => 'Chattogram', 'city' => 'Chattogram City'],
        [['free_delivery' => false, 'free_delivery_min_qty' => null, 'quantity' => 1]],
        0.0,
    );

    expect($insideCost)->toBe(75.0);
    expect($outsideCost)->toBe(140.0);
});

test('an empty cart and an empty item list both fall back to the location rate, not free', function () {
    $cartCost = app(ShippingCostCalculator::class)->forCartWithBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        collect(),
        0.0,
    );
    $itemsCost = app(ShippingCostCalculator::class)->forItemsWithoutBundles(
        ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation'],
        [],
        0.0,
    );

    expect($cartCost)->toBe(75.0);
    expect($itemsCost)->toBe(75.0);
});
