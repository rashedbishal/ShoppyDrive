<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\User;
use App\Support\CartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFixedPriceBundle(Product $a, Product $b, float $bundlePrice = 120.0): ProductBundle
{
    $bundle = ProductBundle::factory()->create([
        'discount_type' => BundleDiscountType::FixedPrice,
        'bundle_price' => $bundlePrice,
    ]);

    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    return $bundle;
}

test('a fixed-price bundle prorates across its components so they sum to the bundle price', function () {
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeFixedPriceBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $priceA = $cartManager->resolvePrice($a->id, null, $bundle->id);
    $priceB = $cartManager->resolvePrice($b->id, null, $bundle->id);

    expect($priceA)->toBe(90.0);
    expect($priceB)->toBe(30.0);
    expect($priceA + $priceB)->toBe(120.0);
});

test('a bundle price wins over an active flash sale on the same product', function () {
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeFixedPriceBundle($a, $b, 120);

    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $a->id, 'sale_price' => 10]);

    expect(app(CartManager::class)->resolvePrice($a->id, null, $bundle->id))->toBe(90.0);
    // Outside the bundle context, the flash sale still applies normally.
    expect(app(CartManager::class)->resolvePrice($a->id, null))->toBe(10.0);
});

test('an inactive bundle no longer overrides pricing', function () {
    $a = Product::factory()->create(['price' => 150]);
    $b = Product::factory()->create(['price' => 50]);
    $bundle = makeFixedPriceBundle($a, $b, 120);
    $bundle->update(['is_active' => false]);

    expect(app(CartManager::class)->resolvePrice($a->id, null, $bundle->id))->toBe(150.0);
});

test('revalidatePrices clears bundle_id and reverts to normal pricing once a bundle group is incomplete', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);
    $bundle = makeFixedPriceBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->getOrCreateUserCart($user);
    // Only one of the two required components is in the cart — the group
    // is broken, so it should never be charged the bundle price.
    $item = $cart->items()->create(['product_id' => $a->id, 'variant_id' => null, 'bundle_id' => $bundle->id, 'quantity' => 1, 'price' => 90]);

    $cartManager->revalidatePrices($cart);

    expect($item->refresh()->bundle_id)->toBeNull();
    expect((float) $item->price)->toBe(150.0);
});

test('revalidatePrices keeps bundle pricing intact when the full group is present', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);
    $bundle = makeFixedPriceBundle($a, $b, 120);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->getOrCreateUserCart($user);
    $itemA = $cart->items()->create(['product_id' => $a->id, 'variant_id' => null, 'bundle_id' => $bundle->id, 'quantity' => 1, 'price' => 90]);
    $itemB = $cart->items()->create(['product_id' => $b->id, 'variant_id' => null, 'bundle_id' => $bundle->id, 'quantity' => 1, 'price' => 30]);

    $cartManager->revalidatePrices($cart);

    expect($itemA->refresh()->bundle_id)->toBe($bundle->id);
    expect($itemB->refresh()->bundle_id)->toBe($bundle->id);
    expect((float) $itemA->price)->toBe(90.0);
    expect((float) $itemB->price)->toBe(30.0);
});

test('a complete free-delivery bundle waives shipping for a cart made up only of its items', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'free_delivery' => false]);
    $b = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'free_delivery' => false]);

    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FreeDelivery, 'bundle_price' => null]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    $cartManager = app(CartManager::class);
    $cart = $cartManager->getOrCreateUserCart($user);
    $cart->items()->create(['product_id' => $a->id, 'variant_id' => null, 'bundle_id' => $bundle->id, 'quantity' => 1, 'price' => 100]);
    $cart->items()->create(['product_id' => $b->id, 'variant_id' => null, 'bundle_id' => $bundle->id, 'quantity' => 1, 'price' => 100]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Bundle Shipper', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->shipping_cost)->toBe(0.0);
});
