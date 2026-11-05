<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function bundleCheckoutPayload(ProductBundle $bundle, array $items, array $overrides = []): array
{
    return array_merge([
        'name' => 'Bundle Buyer',
        'email' => 'bundle-buyer@example.com',
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => array_map(fn (array $item) => array_merge(['bundle_id' => $bundle->id], $item), $items),
    ], $overrides);
}

test('a COD checkout with a complete bundle charges exactly the bundle price and tags order items', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);

    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => 120,
    ]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();

    // REL-001: price included on every line, matching what Checkout.tsx
    // actually sends — 90/30 are this bundle's prorated per-unit prices
    // (asserted below), not the products' plain prices.
    $this->actingAs($user)->post(route('checkout.store'), bundleCheckoutPayload($bundle, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_unit_quantity' => 1, 'price' => 90],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1, 'bundle_unit_quantity' => 1, 'price' => 30],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(120.0);

    $items = $order->items;
    expect($items)->toHaveCount(2);
    expect($items->every(fn ($item) => $item->bundle_id === $bundle->id))->toBeTrue();
    expect((float) $items->firstWhere('product_id', $a->id)->price)->toBe(90.0);
    expect((float) $items->firstWhere('product_id', $b->id)->price)->toBe(30.0);

    expect($a->refresh()->stock_quantity)->toBe(9);
    expect($b->refresh()->stock_quantity)->toBe(9);
});

test('a bundle checkout succeeds when the submitted price matches the prorated bundle price', function () {
    // BIZ-001 and BIZ-002 fixed independent bugs in the same Checkout.tsx
    // payload — this is the combination the real page now actually sends:
    // bundle_id/bundle_unit_quantity (BIZ-002) and a per-line price
    // (BIZ-001) together. The submitted price must match the prorated
    // bundle price CartManager::resolvePrice() computes for a bundled
    // line, not the product's plain price, or this would be refused as
    // stale even though nothing actually changed.
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);

    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => 120,
    ]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), bundleCheckoutPayload($bundle, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_unit_quantity' => 1, 'price' => 90],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1, 'bundle_unit_quantity' => 1, 'price' => 30],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(120.0);
});

test('buying two units of the same bundle scales quantities and the charged total together', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);

    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => 120,
    ]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();

    // REL-001: price included on every line, matching what Checkout.tsx
    // actually sends — the per-unit price stays 90/30 regardless of
    // quantity (price is per unit; quantity multiplies it).
    $this->actingAs($user)->post(route('checkout.store'), bundleCheckoutPayload($bundle, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 2, 'bundle_unit_quantity' => 1, 'price' => 90],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 2, 'bundle_unit_quantity' => 1, 'price' => 30],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();
    // 2x the bundle: proration is per-unit, so the line subtotal doubles with quantity.
    expect((float) $order->subtotal)->toBe(240.0);
    expect($a->refresh()->stock_quantity)->toBe(8);
    expect($b->refresh()->stock_quantity)->toBe(8);
});

test('removing a bundle component before checkout breaks the group and charges normal prices instead', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);

    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => 120,
    ]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();

    // Only product A is submitted, tagged as if it were still part of the
    // bundle — the group is incomplete, so it must not get the bundle price.
    // The first submission only syncs the cart and discovers the price
    // needs correcting (same "stale price" bounce as flash sales), so it
    // redirects back with an error instead of placing an order yet.
    $basePayload = [
        'name' => 'Bundle Buyer', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ];

    $first = $this->actingAs($user)->post(route('checkout.store'), $basePayload + [
        'items' => [
            ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1],
        ],
    ]);

    $first->assertSessionHas('error');
    expect(Order::query()->count())->toBe(0);

    // REL-001: Checkout.tsx always resubmits with an explicit items array
    // (never omits it to fall back on the server-side cart) — this is the
    // corrected (normal, non-bundle) price/grouping the page would have
    // re-read after the bounce above.
    $this->actingAs($user)->post(route('checkout.store'), $basePayload + [
        'items' => [
            ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => null, 'bundle_unit_quantity' => null, 'price' => 150],
        ],
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(150.0);
    expect($order->items->first()->bundle_id)->toBeNull();
});
