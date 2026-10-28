<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;

function enableGuestCheckout(): void
{
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
}

function guestOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Guest Buyer',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'address_line2' => 'Flat 3B',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
    ], $overrides);
}

test('guest checkout is off by default: visiting checkout redirects to login', function () {
    $response = $this->get(route('checkout.index'));

    $response->assertRedirect(route('login'));
});

test('when guest checkout is enabled, a guest can view the checkout page', function () {
    enableGuestCheckout();
    $product = Product::factory()->create();

    // /checkout with nothing to check out (no cart items, no Buy Now item)
    // redirects to home — a Buy Now item is the simplest way to give this
    // guest-access test something to check out without needing a real
    // session-backed cart row.
    $response = $this->get(route('checkout.index', ['buy_now_product' => $product->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('checkout/Checkout'));
});

test('when guest checkout is disabled, a guest still cannot view the checkout page', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => false, 'group' => 'general']);

    $response = $this->get(route('checkout.index'));

    $response->assertRedirect(route('login'));
});

test('a guest can place an order with only a phone number when guest checkout is enabled', function () {
    enableGuestCheckout();
    $product = Product::factory()->create(['price' => 299]);

    $response = $this->post(route('checkout.store'), guestOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number, false));

    expect($order->user_id)->toBeNull();
    expect($order->shipping_address['phone'])->toBe('01700000000');
    expect($order->shipping_address['email'] ?? null)->toBeNull();
});

test('a guest order still requires a phone number', function () {
    enableGuestCheckout();
    $product = Product::factory()->create();

    $response = $this->post(route('checkout.store'), guestOrderPayload([
        'phone' => '',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertInvalid(['phone']);
});

test('an order is rejected when the phone number is not a valid 11-digit Bangladeshi number', function () {
    enableGuestCheckout();
    $product = Product::factory()->create();

    $response = $this->post(route('checkout.store'), guestOrderPayload([
        'phone' => '12345',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertInvalid(['phone']);
    expect(Order::count())->toBe(0);
});

test('a guest cannot place an order while guest checkout is disabled', function () {
    $product = Product::factory()->create();

    $response = $this->post(route('checkout.store'), guestOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertRedirect(route('login'));
    expect(Order::count())->toBe(0);
});

test('a guest cannot reference a saved address id', function () {
    enableGuestCheckout();
    $product = Product::factory()->create();
    $owner = User::factory()->create();
    $address = Address::create([
        'user_id' => $owner->id,
        'name' => 'Someone Else',
        'phone' => '01711111111',
        'address_line1' => 'Secret House',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'is_default' => true,
    ]);

    $response = $this->post(route('checkout.store'), guestOrderPayload([
        'address_id' => $address->id,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]));

    $response->assertInvalid(['address_id']);
});

test('an authenticated unverified user is still sent to verify their email, regardless of guest checkout', function () {
    enableGuestCheckout();
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('checkout.index'));

    $response->assertRedirect(route('verification.notice'));
});

test('the mobile banking payment page is reachable by a guest when guest checkout is enabled', function () {
    enableGuestCheckout();

    // An invalid/unknown token still proves the route itself isn't gated
    // behind login for guests — it bounces to checkout, not to login.
    $response = $this->get(route('payment.mobile.show', 'not-a-real-token'));

    $response->assertRedirect(route('checkout.index'));
});

test('admin can toggle guest checkout on and the value round-trips as a real boolean', function () {
    $admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    // The real admin form submits as multipart/form-data (it also carries a
    // logo upload field), and Inertia's FormData serializer stringifies
    // booleans to "1"/"0" (see @inertiajs/core's objectToFormData) — so the
    // string "0" is what a real "turn it off" submission actually sends,
    // not a native PHP false. A naive save would store that truthy-looking
    // string and the toggle would appear permanently stuck on.
    $response = $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        '_method' => 'PUT',
        'guest_checkout_enabled' => '1',
    ]);

    $response->assertRedirect();
    expect(Setting::where('key', 'guest_checkout_enabled')->first()->value)->toBe(true);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        '_method' => 'PUT',
        'guest_checkout_enabled' => '0',
    ]);

    $response->assertRedirect();
    expect(Setting::where('key', 'guest_checkout_enabled')->first()->value)->toBe(false);
});
