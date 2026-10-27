<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;

test('checkout charges the admin-configured inside-dhaka delivery rate', function () {
    Setting::create(['key' => 'shipping_rate_inside_dhaka', 'value' => 75, 'group' => 'shipping']);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->shipping_cost)->toBe(75.0);
    expect((float) $order->total)->toBe(175.0);
});

test('checkout charges the admin-configured outside-dhaka delivery rate', function () {
    Setting::create(['key' => 'shipping_rate_outside_dhaka', 'value' => 140, 'group' => 'shipping']);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Chattogram',
        'state' => 'Chattogram',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->shipping_cost)->toBe(140.0);
    expect((float) $order->total)->toBe(240.0);
});
