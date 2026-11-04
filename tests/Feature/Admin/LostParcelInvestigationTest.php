<?php

declare(strict_types=1);

/*
 * FSA-11: neither Pathao nor Steadfast reports a distinct "lost" status in
 * this app's confirmed vocabulary (PATHAO_MAP/STEADFAST_MAP), so
 * CourierOrderStatusMapper::sync() already fails safe for any unrecognized
 * report — it's recorded as evidence/history, but the order's status is
 * never changed, never restocked, never refunded. The only remaining gap
 * was visibility: Admin\OrderController::show() now flags an active order
 * whose latest courier report didn't map to anything recognized, so an
 * admin viewing it can investigate manually.
 */

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

function lostParcelOrder(array $overrides = []): Order
{
    $product = Product::factory()->create();

    $order = Order::create(array_merge([
        'order_number' => 'ORD-LOST-'.uniqid(),
        'status' => 'shipped',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
    ], $overrides));

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    return $order;
}

test('an active order with an unrecognized pathao status is flagged for investigation', function () {
    $order = lostParcelOrder(['pathao_order_status' => 'parcel_lost']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', true));
});

test('an active order with an unrecognized steadfast status is flagged for investigation', function () {
    $order = lostParcelOrder(['steadfast_status' => 'parcel_missing']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', true));
});

test('an active order with a recognized courier status is not flagged', function () {
    $order = lostParcelOrder(['pathao_order_status' => 'in_transit']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', false));
});

test('an order with no courier status at all is not flagged', function () {
    $order = lostParcelOrder();

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', false));
});

test('a cancelled order with a stale unrecognized status is not flagged, since it is already a terminal outcome', function () {
    $order = lostParcelOrder(['status' => 'cancelled', 'pathao_order_status' => 'parcel_lost']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', false));
});

test('a delivered order with a stale unrecognized status is not flagged, since it is already a terminal outcome', function () {
    $order = lostParcelOrder(['status' => 'delivered', 'steadfast_status' => 'parcel_missing']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.needsCourierInvestigation', false));
});
