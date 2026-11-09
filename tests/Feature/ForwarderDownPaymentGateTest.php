<?php

declare(strict_types=1);

/*
 * OPS-005: once the forwarder phone is confirmed down (SmsForwarderHeartbeat),
 * a bKash/Nagad Transaction ID submitted now can never be matched — refuse
 * mobile banking at the point of order placement rather than let a customer
 * reach the "we haven't received this yet" dead end. COD must stay
 * unaffected, since it doesn't depend on the forwarder at all.
 */

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\User;
use App\Support\SmsForwarderHeartbeat;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    createMotherAdmin();
    SmsForwarderHeartbeat::record();
    $this->travel(10)->minutes(); // now stale → isDown() is true
});

function forwarderDownCheckoutPayload(User $user, Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ], $overrides);
}

test('a mobile banking checkout is rejected once the forwarder is down', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 50]);

    $response = actingAs($user)->post(route('checkout.store'), forwarderDownCheckoutPayload($user, $product, [
        'payment_method' => 'mobile_banking',
    ]));

    $response->assertInvalid(['payment_method']);
    expect(Order::count())->toBe(0);
    expect(PendingCheckout::count())->toBe(0);
});

test('a COD checkout still succeeds while the forwarder is down', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 50]);

    $response = actingAs($user)->post(route('checkout.store'), forwarderDownCheckoutPayload($user, $product));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number));
});

test('a mobile banking checkout succeeds again once the forwarder is back', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 50]);

    SmsForwarderHeartbeat::record(); // fresh heartbeat — back up

    $response = actingAs($user)->post(route('checkout.store'), forwarderDownCheckoutPayload($user, $product, [
        'payment_method' => 'mobile_banking',
    ]));

    $pending = PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    $response->assertRedirect(route('payment.mobile.show', $pending->token));
});

test('the landing page order form rejects mobile banking once the forwarder is down', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    $response = post(route('lp.order', $page->slug), [
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.com',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'mobile_banking',
        'provider' => 'bkash',
        'order_token' => (string) Str::uuid(),
    ]);

    $response->assertInvalid(['payment_method']);
    expect(Order::count())->toBe(0);
});

test('the landing page order form still accepts COD once the forwarder is down', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    post(route('lp.order', $page->slug), [
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.com',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ])->assertRedirect();

    expect(Order::count())->toBe(1);
});

test('the mobile_banking_available shared prop reflects forwarder status', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertInertia(fn ($page) => $page->where('mobile_banking_available', false));

    SmsForwarderHeartbeat::record();

    actingAs($user)->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertInertia(fn ($page) => $page->where('mobile_banking_available', true));
});
