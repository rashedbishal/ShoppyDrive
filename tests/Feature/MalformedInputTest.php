<?php

declare(strict_types=1);

/*
 * BE-001: malformed requests (a list where text is expected, a token from
 * the wrong flow, a guest admin logout, a hand-typed admin URL) must get
 * the normal answer, never a 500. Bots send these constantly.
 */

use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

test('order tracking with a list instead of a phone number is answered normally', function () {
    Order::create([
        'order_number' => 'ORD-MALFORMED-1', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
    ]);

    $this->get('/order/track?order_number=ORD-MALFORMED-1&phone[]=x')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('order', null));
    $this->get('/order/track?order_number[]=x&phone=01700000000')->assertOk();
    // Same answer as an empty order number.
    $this->getJson('/order/track/check?order_number[]=x')->assertOk()->assertExactJson(['found' => false]);
});

test('a main-checkout payment token opened under a landing-page URL is not found', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();
    $product = Product::factory()->create(['price' => 100]);
    LandingPage::create(['title' => 'LP', 'slug' => 'malformed-lp', 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'A', 'email' => $user->email, 'phone' => '01700000000', 'address_line1' => 'H', 'city' => 'Dhaka',
        'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'mobile_banking', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]],
    ])->assertRedirect();
    $token = PendingCheckout::query()->value('token');

    $this->get("/lp/malformed-lp/payment/{$token}")->assertNotFound();
    $this->post("/lp/malformed-lp/payment/{$token}", ['transaction_id' => 'ABCD1234'])->assertNotFound();
});

test('a landing-page payment token opened under a different landing page is not found', function () {
    $product = Product::factory()->create(['price' => 100]);
    $own = LandingPage::create(['title' => 'Own', 'slug' => 'own-lp', 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);
    LandingPage::create(['title' => 'Other', 'slug' => 'other-lp', 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    $this->post(route('lp.order', $own->slug), [
        'name' => 'A', 'phone' => '01700000000', 'address_line1' => 'H', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'mobile_banking', 'provider' => 'bkash', 'order_token' => (string) Str::uuid(),
    ])->assertRedirect();
    $token = PendingCheckout::query()->value('token');

    $this->get("/lp/own-lp/payment/{$token}")->assertOk();
    $this->get("/lp/other-lp/payment/{$token}")->assertNotFound();
});

test('logging out of admin when not logged in just goes to the admin login page', function () {
    $this->post('/admin/logout')->assertRedirect(route('admin.login'));
});

test('a list in an email field is refused by validation, not a crash', function (string $route) {
    $response = $this->post(route($route), ['email' => ['x'], 'password' => 'x']);

    expect($response->status())->toBeLessThan(500);
})->with(['login.store', 'admin.login.submit', 'password.email']);

test('hand-typed admin "show" URLs that have no page are refused, not a crash', function (string $path) {
    $product = Product::factory()->create();

    // 405: the same URL still exists for save (PUT) and delete (DELETE).
    $this->actingAs(createMotherAdmin(), 'admin')
        ->get(str_replace('{id}', (string) $product->id, $path))
        ->assertMethodNotAllowed();
})->with(['/admin/products/{id}', '/admin/categories/{id}', '/admin/sub-categories/{id}', '/admin/brands/{id}', '/admin/coupons/{id}', '/admin/flash-sales/{id}', '/admin/product-bundles/{id}']);
