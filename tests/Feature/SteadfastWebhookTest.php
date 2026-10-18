<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSteadfastOrder(): Order
{
    $product = Product::factory()->create();

    $order = Order::create([
        'order_number' => 'ORD-WEBHOOK-1',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Rafiq Islam', 'phone' => '01712345678'],
        'billing_address' => [],
        'steadfast_consignment_id' => 'CONSIGN-ABC',
        'steadfast_status' => 'in_review',
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

it('updates the order steadfast status from a delivery_status webhook', function () {
    $order = makeSteadfastOrder();

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'invoice' => 'ORD-WEBHOOK-1',
        'cod_amount' => 560,
        'status' => 'delivered',
        'tracking_message' => 'Your package has been delivered successfully.',
    ]);

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('delivered');
    expect($order->statusHistories()->latest()->first()->notes)->toContain('delivered');
});

it('matches the order by consignment_id when invoice is missing', function () {
    $order = makeSteadfastOrder();

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'cancelled',
    ]);

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('cancelled');
});

it('ignores non delivery_status events without erroring', function () {
    makeSteadfastOrder();

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'tracking_update',
        'consignment_id' => 'CONSIGN-ABC',
    ]);

    $response->assertOk();
});

it('rejects a request with an invalid signature when a webhook secret is configured', function () {
    makeSteadfastOrder();

    Setting::create([
        'key' => 'steadfast_webhook_secret',
        'value' => 'super-secret',
        'group' => 'courier',
    ]);

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ], ['X-Signature' => 'wrong-signature']);

    $response->assertStatus(401);
    expect(Order::first()->steadfast_status)->toBe('in_review');
});

it('accepts a request with a valid signature when a webhook secret is configured', function () {
    $order = makeSteadfastOrder();

    Setting::create([
        'key' => 'steadfast_webhook_secret',
        'value' => 'super-secret',
        'group' => 'courier',
    ]);

    $body = json_encode([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ]);

    $signature = hash_hmac('sha256', $body, 'super-secret');

    $response = $this->call(
        'POST',
        route('steadfast.webhook'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Signature' => $signature,
        ],
        $body,
    );

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('delivered');
});
