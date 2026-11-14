<?php

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * FSA-03: an order number is printed in emails, SMS and courier labels, so
 * it must not be enough to open the confirmation page. Only the owner, or
 * the session that just placed/paid the order (for 24 hours), may.
 */

/**
 * @return array<string, mixed>
 */
function confirmationCheckoutPayload(Product $product, string $paymentMethod = 'cod', ?string $orderToken = null): array
{
    return [
        'name' => 'Confirmation Buyer',
        'email' => 'buyer@example.com',
        'phone' => '01712345678',
        'address_line1' => 'House 5, Road 2',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => $paymentMethod,
        'order_token' => $orderToken ?? (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ];
}

function enableGuestCheckoutForConfirmationTests(): void
{
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
}

beforeEach(function () {
    $this->product = Product::factory()->create(['price' => 250, 'stock_quantity' => 10]);
});

test('the customer who placed the order sees only the allow-listed confirmation fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('checkout.store'), confirmationCheckoutPayload($this->product))->assertRedirect();
    $order = Order::sole();
    $order->forceFill(['guest_token' => 'SECRET-GUEST-TOKEN'])->save();

    $this->get(route('order.confirmation', $order->order_number))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('order/OrderConfirmation')
            ->where('order.order_number', $order->order_number)
            ->where('order.status', 'pending')
            ->where('order.payment_method', 'cod')
            ->where('order.shipping_address.name', 'Confirmation Buyer')
            ->where('order.shipping_address.email', 'buyer@example.com')
            // DISC-38: plus the invoice_available flag (no customer data).
            ->where('order.invoice_available', true)
            ->has('order', 7)
            ->missing('order.shipping_address.phone')
            ->missing('order.items')
            ->missing('order.user_id')
            ->etc())
        ->assertDontSee('SECRET-GUEST-TOKEN')
        ->assertDontSee('01712345678');
});

test('the owner can still open the confirmation from a new session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('checkout.store'), confirmationCheckoutPayload($this->product));
    $order = Order::sole();

    $this->flushSession();

    $this->actingAs($user)->get(route('order.confirmation', $order->order_number))->assertOk();
});

test('a guest who placed the order can open its confirmation in the same session', function () {
    enableGuestCheckoutForConfirmationTests();

    $this->post(route('checkout.store'), confirmationCheckoutPayload($this->product))->assertRedirect();

    $this->get(route('order.confirmation', Order::sole()->order_number))->assertOk();
});

test('an anonymous visitor who only knows the order number gets a 404', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('checkout.store'), confirmationCheckoutPayload($this->product));
    $order = Order::sole();

    auth()->logout();
    $this->flushSession();

    $this->get(route('order.confirmation', $order->order_number))->assertNotFound();
});

test('another logged-in customer gets a 404', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('checkout.store'), confirmationCheckoutPayload($this->product));
    $order = Order::sole();

    $this->flushSession();

    $this->actingAs(User::factory()->create())
        ->get(route('order.confirmation', $order->order_number))
        ->assertNotFound();
});

test('a guest session grant expires after 24 hours', function () {
    enableGuestCheckoutForConfirmationTests();
    $this->post(route('checkout.store'), confirmationCheckoutPayload($this->product));
    $orderNumber = Order::sole()->order_number;

    $this->travel(25)->hours();

    $this->get(route('order.confirmation', $orderNumber))->assertNotFound();
});

test('replaying the same checkout submission re-grants access to that order', function () {
    enableGuestCheckoutForConfirmationTests();
    $token = (string) Str::uuid();
    $this->post(route('checkout.store'), confirmationCheckoutPayload($this->product, orderToken: $token));
    $order = Order::sole();

    $this->flushSession();

    $this->post(route('checkout.store'), confirmationCheckoutPayload($this->product, orderToken: $token))
        ->assertRedirect(route('order.confirmation', $order->order_number));
    $this->get(route('order.confirmation', $order->order_number))->assertOk();
    expect(Order::count())->toBe(1);
});

test('a guest who pays by mobile banking can open the confirmation, and nobody else can', function () {
    enableGuestCheckoutForConfirmationTests();
    $this->post(route('checkout.store'), confirmationCheckoutPayload($this->product, 'mobile_banking'))->assertRedirect();
    $pending = PendingCheckout::sole();
    ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => 'TrxID GUEST123',
        'received_at' => now(), 'parsed_transaction_id' => 'GUEST123',
        'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'GUEST123'])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));
    $orderNumber = Order::sole()->order_number;

    $this->get(route('order.confirmation', $orderNumber))->assertOk();

    $this->flushSession();
    $this->get(route('order.confirmation', $orderNumber))->assertNotFound();
});

test('a serialized order never includes its guest_token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('checkout.store'), confirmationCheckoutPayload($this->product));
    $order = Order::sole();
    $order->forceFill(['guest_token' => 'SECRET-GUEST-TOKEN'])->save();

    $this->get(route('account.orders.show', $order->order_number))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('order.guest_token')->etc())
        ->assertDontSee('SECRET-GUEST-TOKEN');
});
