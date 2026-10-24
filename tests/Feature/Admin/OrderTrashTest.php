<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

function makeTrashTestOrder(string $orderNumber, string $status): Order
{
    $product = Product::factory()->create();

    $order = Order::create([
        'order_number' => $orderNumber,
        'status' => $status,
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

test('a cancelled order can be deleted (soft-deleted) by an admin', function () {
    actingAs($this->admin, 'admin');
    $order = makeTrashTestOrder('ORD-DEL-1', 'cancelled');

    $response = $this->delete(route('admin.orders.destroy', $order));

    $response->assertRedirect(route('admin.orders.index'));
    expect(Order::find($order->id))->toBeNull();
    expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    expect(Order::withTrashed()->find($order->id)->trashed())->toBeTrue();
});

test('a non-cancelled order cannot be deleted', function () {
    actingAs($this->admin, 'admin');
    $order = makeTrashTestOrder('ORD-DEL-2', 'processing');

    $response = $this->delete(route('admin.orders.destroy', $order));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Order::find($order->id))->not->toBeNull();
});

test('trashed orders index only lists soft-deleted orders', function () {
    actingAs($this->admin, 'admin');
    $active = makeTrashTestOrder('ORD-ACTIVE', 'processing');
    $trashed = makeTrashTestOrder('ORD-TRASHED', 'cancelled');
    $trashed->delete();

    $response = $this->get(route('admin.orders.trashed'));

    $response->assertInertia(fn ($page) => $page
        ->component('admin/orders/trashed')
        ->has('orders.data', 1)
        ->where('orders.data.0.order_number', 'ORD-TRASHED')
    );

    expect($active)->not->toBeNull();
});

test('a trashed order can be restored', function () {
    actingAs($this->admin, 'admin');
    $order = makeTrashTestOrder('ORD-RESTORE-1', 'cancelled');
    $order->delete();

    $response = $this->patch(route('admin.orders.restore', $order->id));

    $response->assertRedirect();
    expect(Order::find($order->id))->not->toBeNull();
    expect(Order::find($order->id)->trashed())->toBeFalse();
});

test('a trashed order can be permanently deleted, cascading its items', function () {
    actingAs($this->admin, 'admin');
    $order = makeTrashTestOrder('ORD-FORCE-1', 'cancelled');
    $orderId = $order->id;
    $order->delete();

    $response = $this->delete(route('admin.orders.forceDelete', $orderId));

    $response->assertRedirect(route('admin.orders.trashed'));
    expect(Order::withTrashed()->find($orderId))->toBeNull();
    expect(OrderItem::where('order_id', $orderId)->count())->toBe(0);
});

test('guests cannot access any order trash routes', function () {
    $order = makeTrashTestOrder('ORD-GUEST-1', 'cancelled');

    $this->delete(route('admin.orders.destroy', $order))->assertRedirect(route('admin.login'));
    $this->get(route('admin.orders.trashed'))->assertRedirect(route('admin.login'));
});
