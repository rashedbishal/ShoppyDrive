<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    Setting::insert([
        ['key' => 'steadfast_api_key', 'value' => json_encode('api-key'), 'group' => 'courier'],
        ['key' => 'steadfast_secret_key', 'value' => json_encode('secret-key'), 'group' => 'courier'],
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

test('admin can send an order to steadfast for pickup', function () {
    actingAs($this->admin, 'admin');

    Http::fake([
        '*/create_order' => Http::response([
            'status' => 200,
            'consignment' => [
                'consignment_id' => 'CONSIGN-ABC',
                'tracking_code' => 'TRK-1',
                'status' => 'in_review',
            ],
        ]),
    ]);

    $response = $this->post(route('admin.orders.steadfast.send', $this->order->id));

    $response->assertRedirect();

    $this->order->refresh();
    expect($this->order->steadfast_consignment_id)->toBe('CONSIGN-ABC');
    expect($this->order->steadfast_tracking_code)->toBe('TRK-1');
    expect($this->order->steadfast_status)->toBe('in_review');
    expect($this->order->tracking_number)->toBe('TRK-1');
    expect($this->order->statusHistories()->latest()->first()->notes)->toContain('CONSIGN-ABC');
});

test('a connection failure while sending to steadfast shows a clean error instead of a 500', function () {
    actingAs($this->admin, 'admin');

    Http::fake([
        '*/create_order' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    $response = $this->post(route('admin.orders.steadfast.send', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Steadfast');
    expect($this->order->refresh()->steadfast_consignment_id)->toBeNull();
});

test('sending to steadfast twice is rejected', function () {
    actingAs($this->admin, 'admin');

    $this->order->update(['steadfast_consignment_id' => 'ALREADY-SENT']);

    $response = $this->post(route('admin.orders.steadfast.send', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('admin can refresh steadfast order status', function () {
    actingAs($this->admin, 'admin');

    $this->order->update([
        'steadfast_consignment_id' => 'CONSIGN-ABC',
        'steadfast_status' => 'in_review',
    ]);

    Http::fake([
        '*/status_by_cid/CONSIGN-ABC' => Http::response([
            'status' => 200,
            'delivery_status' => 'delivered',
        ]),
    ]);

    $response = $this->post(route('admin.orders.steadfast.refresh', $this->order->id));

    $response->assertRedirect();
    expect($this->order->refresh()->steadfast_status)->toBe('delivered');
});

test('refreshing status before sending to steadfast is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.orders.steadfast.refresh', $this->order->id));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
