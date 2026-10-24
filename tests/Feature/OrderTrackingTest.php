<?php

declare(strict_types=1);

use App\Models\Order;

function createTrackableOrder(array $shippingAddressOverrides = []): Order
{
    return Order::create([
        'order_number' => 'ORD-20261022-TEST01',
        'user_id' => null,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => array_merge([
            'name' => 'Guest Buyer',
            'phone' => '01700000000',
            'email' => '',
        ], $shippingAddressOverrides),
        'billing_address' => [],
    ]);
}

test('an order with an email on file can still be tracked by email, unchanged', function () {
    $order = createTrackableOrder(['email' => 'buyer@example.com']);

    $response = $this->get(route('order.track', [
        'order_number' => $order->order_number,
        'email' => 'Buyer@Example.com',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('order.order_number', $order->order_number)
        ->where('error', null));
});

test('a guest order with no email on file can be tracked by phone number instead', function () {
    $order = createTrackableOrder(); // email left as '' — phone-only guest order

    $response = $this->get(route('order.track', [
        'order_number' => $order->order_number,
        'phone' => '+880 1700-000000',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('order.order_number', $order->order_number)
        ->where('error', null));
});

test('phone lookup tolerates different formatting of the same number', function () {
    $order = createTrackableOrder(['phone' => '01700000000']);

    $response = $this->get(route('order.track', [
        'order_number' => $order->order_number,
        'phone' => '8801700000000',
    ]));

    $response->assertInertia(fn ($page) => $page->where('order.order_number', $order->order_number));
});

test('a mismatched phone number does not reveal the order', function () {
    $order = createTrackableOrder(['phone' => '01700000000']);

    $response = $this->get(route('order.track', [
        'order_number' => $order->order_number,
        'phone' => '01999999999',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('order', null)
        ->has('error'));
});

test('submitting only an order number with neither email nor phone asks for one', function () {
    $order = createTrackableOrder();

    $response = $this->get(route('order.track', [
        'order_number' => $order->order_number,
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('order', null)
        ->where('error', 'Please also enter the email or phone number used for this order.'));
});

test('the live requirements check reports phone-only for a guest order with no email on file', function () {
    $order = createTrackableOrder(); // email left as '' — phone-only guest order

    $response = $this->getJson(route('order.track.check', [
        'order_number' => $order->order_number,
    ]));

    $response->assertOk()->assertJson([
        'found' => true,
        'requires' => ['email' => false, 'phone' => true],
    ]);
});

test('the live requirements check reports both fields for an order with email on file', function () {
    $order = createTrackableOrder(['email' => 'buyer@example.com']);

    $response = $this->getJson(route('order.track.check', [
        'order_number' => $order->order_number,
    ]));

    $response->assertOk()->assertJson([
        'found' => true,
        'requires' => ['email' => true, 'phone' => true],
    ]);
});

test('the live requirements check reports not found for an unknown order number, without leaking anything else', function () {
    $response = $this->getJson(route('order.track.check', [
        'order_number' => 'ORD-DOES-NOT-EXIST',
    ]));

    $response->assertOk()->assertExactJson(['found' => false]);
});

test('the live requirements check with no order number reports not found', function () {
    $response = $this->getJson(route('order.track.check'));

    $response->assertOk()->assertExactJson(['found' => false]);
});
