<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
        ['key' => 'pathao_store_id', 'value' => json_encode('555'), 'group' => 'courier'],
        ['key' => 'pathao_token', 'value' => json_encode([
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String(),
        ]), 'group' => 'courier'],
    ]);
});

function makeCommandTestOrder(string $orderNumber, string $status, ?string $consignmentId): Order
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
        'pathao_consignment_id' => $consignmentId,
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

it('syncs status for active pathao orders and skips terminal/unsent ones', function () {
    $active = makeCommandTestOrder('ORD-ACTIVE', 'processing', 'CONSIGN-ACTIVE');
    makeCommandTestOrder('ORD-DELIVERED', 'delivered', 'CONSIGN-DELIVERED');
    makeCommandTestOrder('ORD-NO-CONSIGNMENT', 'pending', null);

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-ACTIVE/info' => Http::response([
            'type' => 'success',
            'data' => ['order_status_slug' => 'in_transit'],
        ]),
    ]);

    $this->artisan('pathao:sync-statuses')->assertSuccessful();

    expect($active->refresh()->status->value)->toBe('shipped');

    Http::assertSentCount(1);
});
