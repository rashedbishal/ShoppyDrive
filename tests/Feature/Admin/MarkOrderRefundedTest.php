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

    $product = Product::factory()->create();

    $this->order = Order::create([
        'order_number' => 'ORD-REFUND-1',
        'status' => 'delivered',
        'payment_status' => 'paid',
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
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);
});

test('admin can mark a paid order as refunded', function () {
    actingAs($this->admin, 'admin');

    $response = $this->patch(route('admin.orders.markRefunded', $this->order->id));

    $response->assertRedirect();
    expect($this->order->refresh()->status->value)->toBe('refunded');
    expect($this->order->statusHistories()->latest()->first()->notes)->toContain('refunded');
});

test('marking an already refunded order as refunded is rejected', function () {
    actingAs($this->admin, 'admin');
    $this->order->update(['status' => 'refunded']);

    $response = $this->patch(route('admin.orders.markRefunded', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
