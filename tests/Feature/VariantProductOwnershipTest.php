<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\CartManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/*
 * FSA-01: a variant submitted with a product it doesn't belong to used to be
 * priced from that product (a product-wide flash sale matches any variant id)
 * while stock, SKU and the shipped item came from the foreign variant.
 */

const FOREIGN_VARIANT_ERROR = 'The selected variant is invalid for the given product.';

/**
 * A cheap product on a product-wide flash sale, and an expensive product
 * whose variant is the one an attacker would pair with it.
 *
 * @return array{cheap: Product, expensive: Product, variant: ProductVariant}
 */
function foreignVariantSetup(): array
{
    $cheap = Product::factory()->create(['price' => 500, 'stock_quantity' => 50]);
    $expensive = Product::factory()->create(['price' => 50000, 'stock_quantity' => 0]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $expensive->id,
        'price' => 50000,
        'stock_quantity' => 5,
    ]);

    $sale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $cheap->id, 'variant_id' => null, 'sale_price' => 100]);

    return ['cheap' => $cheap, 'expensive' => $expensive, 'variant' => $variant];
}

/**
 * @param  array<int, array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function variantOwnershipCheckoutPayload(array $items): array
{
    return [
        'name' => 'Checkout User',
        'email' => 'checkout@example.com',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => $items,
    ];
}

test('cart.store rejects a variant that belongs to another product', function () {
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.store'), ['product_id' => $cheap->id, 'variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors(['variant_id' => FOREIGN_VARIANT_ERROR]);

    $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
});

test('cart.store still adds a variant with its own product', function () {
    ['expensive' => $expensive, 'variant' => $variant] = foreignVariantSetup();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.store'), ['product_id' => $expensive->id, 'variant_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('cart_items', ['product_id' => $expensive->id, 'variant_id' => $variant->id, 'price' => 50000]);
});

test('authenticated checkout rejects a foreign variant and creates no order', function () {
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('checkout.store'), variantOwnershipCheckoutPayload([
            ['product_id' => $cheap->id, 'variant_id' => $variant->id, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors(['items.0.variant_id' => FOREIGN_VARIANT_ERROR]);

    expect(Order::count())->toBe(0)
        ->and($variant->fresh()->stock_quantity)->toBe(5);
});

test('guest checkout rejects a foreign variant and creates no order', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();

    $this->post(route('checkout.store'), variantOwnershipCheckoutPayload([
        ['product_id' => $cheap->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]))->assertSessionHasErrors(['items.0.variant_id' => FOREIGN_VARIANT_ERROR]);

    expect(Order::count())->toBe(0)
        ->and($variant->fresh()->stock_quantity)->toBe(5);
});

test('checkout of a variant with its own product charges the variant price', function () {
    ['expensive' => $expensive, 'variant' => $variant] = foreignVariantSetup();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('checkout.store'), variantOwnershipCheckoutPayload([
            ['product_id' => $expensive->id, 'variant_id' => $variant->id, 'quantity' => 1],
        ]))
        ->assertSessionHasNoErrors();

    $order = Order::with('items')->sole();

    expect($order->subtotal)->toBe(50000.0)
        ->and($order->items->sole()->variant_id)->toBe($variant->id)
        ->and($variant->fresh()->stock_quantity)->toBe(4);
});

test('a mismatched cart row stored before the fix is never checked out', function () {
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();
    $user = User::factory()->create();
    $cart = Cart::query()->create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $cheap->id, 'variant_id' => $variant->id, 'quantity' => 1, 'price' => 100]);

    $payload = variantOwnershipCheckoutPayload([]);
    unset($payload['items']);

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertStatus(422);

    expect(Order::count())->toBe(0)
        ->and($variant->fresh()->stock_quantity)->toBe(5);
});

test('pricing refuses a variant of another product', function () {
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();

    app(CartManager::class)->resolvePrice($cheap->id, $variant->id);
})->throws(ModelNotFoundException::class);

test('stock decrement refuses a variant of another product and leaves stock untouched', function () {
    ['cheap' => $cheap, 'variant' => $variant] = foreignVariantSetup();

    expect(fn () => app(InventoryService::class)->decrementForOrderItem(new Order(['order_number' => 'ORD-TEST']), $cheap->id, $variant->id, 1, 100.0))
        ->toThrow(InsufficientStockException::class, 'One of the selected product options is not available.');

    expect($variant->fresh()->stock_quantity)->toBe(5);
});
