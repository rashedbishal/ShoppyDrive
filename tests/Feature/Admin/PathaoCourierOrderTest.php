<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
        ['key' => 'pathao_store_id', 'value' => json_encode('555'), 'group' => 'courier'],
    ]);

    $product = Product::factory()->create();

    $this->order = Order::create([
        'order_number' => 'ORD-ADMIN-1',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [
            'name' => 'Rafiq Islam',
            'phone' => '01712345678',
            'address_line1' => 'House 1, Road 2',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
        ],
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

test('admin can send an order to pathao for pickup', function () {
    actingAs($this->admin, 'admin');

    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 432000,
        ]),
        '*/aladdin/api/v1/orders' => Http::response([
            'type' => 'success',
            'data' => [
                'consignment_id' => 'CONSIGN-ABC',
                'merchant_order_id' => 'ORD-ADMIN-1',
                'order_status' => 'Pending',
                'delivery_fee' => 65,
            ],
        ]),
    ]);

    $response = $this->post(route('admin.orders.pathao.send', $this->order->id));

    $response->assertRedirect();

    $this->order->refresh();
    expect($this->order->pathao_consignment_id)->toBe('CONSIGN-ABC');
    expect($this->order->pathao_order_status)->toBe('Pending');
    expect($this->order->pathao_delivery_fee)->toBe(65.0);
    expect($this->order->tracking_number)->toBe('CONSIGN-ABC');
    expect($this->order->statusHistories()->latest()->first()->notes)->toContain('CONSIGN-ABC');
});

test('sending to pathao twice is rejected', function () {
    actingAs($this->admin, 'admin');

    $this->order->update(['pathao_consignment_id' => 'ALREADY-SENT']);

    $response = $this->post(route('admin.orders.pathao.send', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('admin can refresh pathao order status', function () {
    actingAs($this->admin, 'admin');

    $this->order->update([
        'pathao_consignment_id' => 'CONSIGN-ABC',
        'pathao_order_status' => 'Pending',
    ]);

    Setting::create([
        'key' => 'pathao_token',
        'value' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
        'group' => 'courier',
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-ABC/info' => Http::response([
            'type' => 'success',
            'data' => ['order_status' => 'Delivered', 'order_status_slug' => 'Delivered'],
        ]),
    ]);

    $response = $this->post(route('admin.orders.pathao.refresh', $this->order->id));

    $response->assertRedirect();
    expect($this->order->refresh()->pathao_order_status)->toBe('Delivered');
});

test('refreshing status before sending to pathao is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.orders.pathao.refresh', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
