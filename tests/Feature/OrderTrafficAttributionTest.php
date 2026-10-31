<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function placeOrderWithTrafficCookies($test, User $user, Product $product, ?string $source, ?string $referrer)
{
    Cart::query()->where('user_id', $user->id)->delete();

    $request = $test->actingAs($user);

    if ($source !== null) {
        $request = $request->withCookie('sd_traffic_source', $source);
    }

    if ($referrer !== null) {
        $request = $request->withCookie('sd_traffic_referrer', $referrer);
    }

    return $request->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);
}

test('an order placed after a facebook ad click is attributed to facebook', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    placeOrderWithTrafficCookies($this, $user, $product, 'facebook', null)->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->traffic_source)->toBe('facebook');
});

test('an order placed after a google referral is attributed to google with the referrer host stored', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    placeOrderWithTrafficCookies($this, $user, $product, 'google', 'google.com')->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->traffic_source)->toBe('google');
    expect($order->traffic_referrer)->toBe('google.com');
});

test('an order placed with no traffic source cookie defaults to direct', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    placeOrderWithTrafficCookies($this, $user, $product, null, null)->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->traffic_source)->toBe('direct');
    expect($order->traffic_referrer)->toBeNull();
});
