<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

test('authenticated user can place an order from checkout using submitted cart items', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create([
        'price' => 299,
        'stock_quantity' => 10,
    ]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this
        ->actingAs($user)
        ->post(route('checkout.store'), [
            'name' => 'Checkout User',
            'email' => $user->email,
            'phone' => '01700000000',
            'address_line1' => 'House 12, Road 7',
            'address_line2' => 'Flat 3B',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postal_code' => '1212',
            'country' => 'Bangladesh',
            'save_address' => true,
            'payment_method' => 'cod',
            'order_token' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => 2,
                ],
            ],
        ]);

    $order = Order::query()->firstOrFail();

    $response->assertRedirect(route('order.confirmation', $order->order_number, false));

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'user_id' => $user->id,
        'payment_method' => 'cod',
        'subtotal' => 598.00,
        // City "Dhaka" is outside Dhaka City Corporation, so the default
        // outside-Dhaka delivery charge applies (see Setting::shippingRateFor()).
        'shipping_cost' => 120.00,
        'total' => 718.00,
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 299.00,
        'subtotal' => 598.00,
    ]);

    $this->assertDatabaseMissing('cart_items', [
        'product_id' => $product->id,
    ]);
});
