<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\User;
use App\Support\CartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeAutoAssembleBundle(Product $a, Product $b, float $bundlePrice = 120.0): ProductBundle
{
    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => $bundlePrice,
    ]);

    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    return $bundle;
}

test('adding two plain items that together match a bundle recipe auto-groups them at the bundle price', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeAutoAssembleBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->syncUserCart($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
    ]);

    $serialized = $cartManager->serialize($cart);
    $items = collect($serialized['items']);

    expect($items)->toHaveCount(2);
    expect($items->every(fn (array $item) => $item['bundle_id'] === $bundle->id))->toBeTrue();
    expect($items->firstWhere('product.id', $a->id)['price'])->toBe(90.0);
    expect($items->firstWhere('product.id', $b->id)['price'])->toBe(30.0);
    expect($serialized['subtotal'])->toBe(120.0);
});

test('a surplus component quantity forms the bundle and leaves the extra as a separate plain item', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeAutoAssembleBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->syncUserCart($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 2],
    ]);

    $serialized = $cartManager->serialize($cart);
    $items = collect($serialized['items']);

    expect($items)->toHaveCount(3);

    $bundled = $items->filter(fn (array $item) => $item['bundle_id'] === $bundle->id);
    $plain = $items->filter(fn (array $item) => $item['bundle_id'] === null);

    expect($bundled)->toHaveCount(2);
    expect($plain)->toHaveCount(1);
    expect($plain->first()['product']['id'])->toBe($b->id);
    expect($plain->first()['quantity'])->toBe(1);
    expect($plain->first()['price'])->toBe(50.0);
});

test('scaling both components up together forms multiple bundle units with no leftover', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeAutoAssembleBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->syncUserCart($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 2],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 2],
    ]);

    $serialized = $cartManager->serialize($cart);
    $items = collect($serialized['items']);

    expect($items)->toHaveCount(2);
    expect($items->every(fn (array $item) => $item['bundle_id'] === $bundle->id))->toBeTrue();
    expect($items->sum('quantity'))->toBe(4);
    expect($serialized['subtotal'])->toBe(240.0);
});

test('when two bundles could both claim the same items, the lower-id bundle wins', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150]);
    $c = Product::factory()->create(['price' => 50]);
    $d = Product::factory()->create(['price' => 70]);

    $firstBundle = makeAutoAssembleBundle($a, $c, 120);

    $secondBundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => 150,
    ]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $secondBundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $secondBundle->id, 'product_id' => $d->id, 'quantity' => 1]);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->syncUserCart($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $c->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $d->id, 'variant_id' => null, 'quantity' => 1],
    ]);

    $serialized = $cartManager->serialize($cart);
    $items = collect($serialized['items']);

    expect($items->firstWhere('product.id', $a->id)['bundle_id'])->toBe($firstBundle->id);
    expect($items->firstWhere('product.id', $c->id)['bundle_id'])->toBe($firstBundle->id);
    expect($items->firstWhere('product.id', $d->id)['bundle_id'])->toBeNull();
});

test('a free-delivery bundle auto-assembles from plain items too', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 100, 'free_delivery' => false]);
    $b = Product::factory()->create(['price' => 100, 'free_delivery' => false]);

    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FreeDelivery, 'bundle_price' => null]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->syncUserCart($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
    ]);

    $serialized = $cartManager->serialize($cart);
    $items = collect($serialized['items']);

    expect($items->every(fn (array $item) => $item['bundle_id'] === $bundle->id))->toBeTrue();
    // Free-delivery bundles don't override price — only shipping eligibility.
    expect($items->firstWhere('product.id', $a->id)['price'])->toBe(100.0);
});

test('checking out with plain items that newly satisfy a bundle bounces back for review, then succeeds at the bundle price', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);
    $bundle = makeAutoAssembleBundle($a, $b, 120);

    $basePayload = [
        'name' => 'Combo Buyer', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ];

    $first = $this->actingAs($user)->post(route('checkout.store'), $basePayload + [
        'items' => [
            ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);

    $first->assertSessionHas('error');
    expect(Order::query()->count())->toBe(0);

    $this->actingAs($user)->post(route('checkout.store'), $basePayload)->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(120.0);
    expect($order->items->every(fn ($item) => $item->bundle_id === $bundle->id))->toBeTrue();
});
