<?php

declare(strict_types=1);

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

function unseenOrderFor(string $number): Order
{
    $product = Product::factory()->create();

    $order = Order::create([
        'order_number' => $number,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
    ]);

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

test('a new order starts unseen', function () {
    $order = unseenOrderFor('ORD-UNSEEN-1')->refresh();

    expect($order->is_seen)->toBeFalse();
    expect($order->seen_at)->toBeNull();
});

test('viewing an order marks it seen and the sidebar badge count drops', function () {
    $order = unseenOrderFor('ORD-UNSEEN-2');
    unseenOrderFor('ORD-UNSEEN-3');

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('unseen_orders_count', 2));

    $this->get(route('admin.orders.show', $order->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.is_seen', true)
            ->where('order.seen_at', fn ($value) => $value !== null));

    expect($order->refresh()->is_seen)->toBeTrue();
    expect($order->refresh()->seen_at)->not->toBeNull();

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('unseen_orders_count', 1));
});

test('viewing an already-seen order does not touch its seen_at timestamp', function () {
    $order = unseenOrderFor('ORD-UNSEEN-4');

    actingAs($this->admin, 'admin');

    $this->get(route('admin.orders.show', $order->id));
    $firstSeenAt = $order->refresh()->seen_at;

    $this->travel(1)->hours();
    $this->get(route('admin.orders.show', $order->id));

    expect($order->refresh()->seen_at->equalTo($firstSeenAt))->toBeTrue();
});

test('the unseen-orders count is null for a guest/customer request', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('unseen_orders_count', null));
});
