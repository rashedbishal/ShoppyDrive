<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
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
    ], $overrides);
}

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

test('buy now leaves the customer\'s existing cart completely untouched', function () {
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
        'is_buy_now' => true,
        'items' => [
            ['product_id' => $buyNowProduct->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertRedirect();

    // The buy-now order was placed for the buy-now product only...
    $order = Order::query()->firstOrFail();
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $buyNowProduct->id,
        'quantity' => 1,
    ]);
    $this->assertDatabaseMissing('order_items', [
        'order_id' => $order->id,
        'product_id' => $cartedProduct->id,
    ]);

    // ...and the real cart (with its unrelated item) is exactly as it was.
    $this->assertDatabaseHas('carts', ['id' => $cart->id, 'user_id' => $user->id]);
    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $cartedProduct->id,
        'quantity' => 3,
    ]);
});

test('buy now requires items to be present', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'is_buy_now' => true,
    ]));

    $response->assertInvalid(['items']);
});
