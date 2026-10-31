<?php

declare(strict_types=1);

use App\Models\BlockedIp;
use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('a blocked ip address cannot place an order via checkout', function () {
    BlockedIp::create(['ip_address' => '203.0.113.5', 'reason' => 'Chargeback abuse']);

    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 299, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->actingAs($user)
        ->post(route('checkout.store'), [
            'name' => 'Checkout User',
            'email' => $user->email,
            'phone' => '01700000000',
            'address_line1' => 'House 12, Road 7',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
            ],
        ]);

    $response->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

test('a blocked ip address cannot submit a mobile banking payment', function () {
    BlockedIp::create(['ip_address' => '203.0.113.6']);

    $user = User::factory()->create();

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])
        ->actingAs($user)
        ->post(route('payment.mobile.submit', 'any-token'), [
            'provider' => 'bkash',
            'transaction_id' => 'ABC12345',
        ]);

    $response->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

test('a blocked ip address cannot place a landing-page order', function () {
    BlockedIp::create(['ip_address' => '203.0.113.7']);

    $product = Product::factory()->create([
        'price' => 100, 'stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay(), 'free_delivery' => true,
    ]);
    $page = LandingPage::create([
        'title' => 'LP Block Test', 'slug' => 'lp-block-'.Str::random(8), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('lp.order', $page->slug), [
            'name' => 'Guest Buyer',
            'phone' => '01700000000',
            'address_line1' => 'House 1',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'quantity' => 1,
            'payment_method' => 'cod',
        ]);

    $response->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

test('a blocked ip address cannot submit a landing-page payment', function () {
    BlockedIp::create(['ip_address' => '203.0.113.8']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->post(route('lp.payment.submit', ['any-slug', 'any-token']), [
            'transaction_id' => 'ABC12345',
        ]);

    $response->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

test('a blocked ip address is declined on every storefront and auth page, not just order placement', function () {
    BlockedIp::create(['ip_address' => '203.0.113.9']);

    $blocked = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);

    $blocked->get('/')->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('errors/ErrorPage')
        ->where('status', 403));

    $blocked->get(route('login'))->assertForbidden();
    $blocked->get(route('cart.index'))->assertForbidden();
});

test('a blocked ip address can still reach the admin area, which manages the block list', function () {
    BlockedIp::create(['ip_address' => '203.0.113.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get(route('admin.login'))
        ->assertOk();
});

test('unblocking an ip address immediately restores access', function () {
    $blockedIp = BlockedIp::create(['ip_address' => '203.0.113.11']);

    $visitor = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11']);

    $visitor->get('/')->assertForbidden();

    $blockedIp->delete();

    $visitor->get('/')->assertOk();
});

test('an order records the ip address and user agent of the request that created it', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $user->id)->delete();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
        ->actingAs($user)
        ->post(route('checkout.store'), [
            'name' => 'Checkout User',
            'email' => $user->email,
            'phone' => '01700000000',
            'address_line1' => 'House 12, Road 7',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
            'payment_method' => 'cod',
            'order_token' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
            ],
        ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->ip_address)->toBe('198.51.100.7');
    expect($order->user_agent)->toBe('TestBrowser/1.0');
});
