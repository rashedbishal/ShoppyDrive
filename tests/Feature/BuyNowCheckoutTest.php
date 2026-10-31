<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function buyNowOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'address_line2' => 'Flat 3B',
        // "Inside Dhaka City Corporation" is the magic value the checkout
        // form's district/city picker sends for free shipping — plain
        // "Dhaka" as the city does not match and falls back to the paid rate.
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('a guest clicking buy now is redirected to login instead of the checkout page', function () {
    $product = Product::factory()->create(['price' => 500]);

    $response = $this->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_qty' => 1]));

    $response->assertRedirect(route('login'));
});

test('checkout page resolves a buy-now item from the query string without creating a cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)
        ->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_qty' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buyNowItem.product.id', $product->id)
            ->where('buyNowItem.quantity', 2)
            ->where('buyNowItem.price', 500)
            ->where('buyNowItem.subtotal', 1000)
        );

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

test('buy now places an order without ever creating a cart row', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 299]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'is_buy_now' => true,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number, false));

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'user_id' => $user->id,
        'subtotal' => 598.00,
        'shipping_cost' => 60,
        'total' => 658.00,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'subtotal' => 598.00,
    ]);

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

test('a buy-now item is merged into the order alongside what was already in the cart', function () {
    // Checkout.tsx merges the Buy Now item with whatever's in the cart
    // store (summing quantity if it's the same product/variant) before
    // ever submitting — so what reaches checkout.store() here is already
    // the combined list, not a separate "is_buy_now" item on its own.
    $user = User::factory()->create();
    $cartedProduct = Product::factory()->create(['price' => 150]);
    $buyNowProduct = Product::factory()->create(['price' => 299]);

    Cart::query()->where('user_id', $user->id)->delete();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create([
        'product_id' => $cartedProduct->id,
        'variant_id' => null,
        'quantity' => 3,
        'price' => 150,
    ]);

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'items' => [
            ['product_id' => $cartedProduct->id, 'variant_id' => null, 'quantity' => 3],
            ['product_id' => $buyNowProduct->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertRedirect();

    $order = Order::query()->firstOrFail();
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $buyNowProduct->id,
        'quantity' => 1,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $cartedProduct->id,
        'quantity' => 3,
    ]);

    // The order went through, so the cart (now fully represented in the
    // order) is cleared — same as any other successful checkout.
    expect(Cart::where('id', $cart->id)->exists())->toBeFalse();
});

test('a buy-now item for a product already in the cart sums into a single combined quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100]);

    Cart::query()->where('user_id', $user->id)->delete();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'quantity' => 1,
        'price' => 100,
    ]);

    // Buying the same product again merges into one line of quantity 2,
    // exactly like clicking "Add to Cart" a second time would.
    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]));

    $response->assertRedirect();

    $order = Order::query()->firstOrFail();
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'subtotal' => 200.00,
    ]);
    expect($order->items()->count())->toBe(1);
});

test('checkout with no items and no existing cart is rejected', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
    ]));

    $response->assertStatus(422);
    expect(Order::query()->count())->toBe(0);
});
