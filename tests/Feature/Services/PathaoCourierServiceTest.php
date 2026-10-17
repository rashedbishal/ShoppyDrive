<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PathaoCourierService;
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
    ]);

    $product = Product::factory()->create(['weight' => 1.5]);

    $this->order = Order::create([
        'order_number' => 'ORD-TEST-1',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 80,
        'discount' => 0,
        'tax' => 0,
        'total' => 1080,
        'shipping_address' => [
            'name' => 'Jamal Uddin',
            'phone' => '+8801712345678',
            'address_line1' => 'House 12, Road 5',
            'address_line2' => 'Sector 3',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postal_code' => '1230',
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
        'quantity' => 2,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => 500],
    ]);

    $this->order = $this->order->fresh();
});

it('issues a new access token via password grant when none is cached', function () {
    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 432000,
            'access_token' => 'issued-token',
            'refresh_token' => 'issued-refresh',
        ]),
        '*/aladdin/api/v1/orders' => Http::response([
            'message' => 'Order Created Successfully',
            'type' => 'success',
            'code' => 200,
            'data' => [
                'consignment_id' => 'CONSIGN-1',
                'merchant_order_id' => 'ORD-TEST-1',
                'order_status' => 'Pending',
                'delivery_fee' => 80,
            ],
        ]),
    ]);

    $result = (new PathaoCourierService)->createOrder($this->order);

    expect($result['consignment_id'])->toBe('CONSIGN-1');
    expect($result['order_status'])->toBe('Pending');
    expect($result['delivery_fee'])->toBe(80.0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'issue-token')
        && $request['grant_type'] === 'password'
        && $request['username'] === 'merchant@example.com');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/aladdin/api/v1/orders')
        && $request->hasHeader('Authorization', 'Bearer issued-token')
        && $request['store_id'] === 555
        && $request['recipient_name'] === 'Jamal Uddin'
        && $request['item_quantity'] === 2
        && $request['amount_to_collect'] === 1080);

    expect(Setting::where('key', 'pathao_token')->first()->value['access_token'])->toBe('issued-token');
});

it('reuses a cached, non-expired access token instead of issuing a new one', function () {
    Setting::create([
        'key' => 'pathao_token',
        'value' => [
            'access_token' => 'cached-token',
            'refresh_token' => 'cached-refresh',
            'expires_at' => now()->addHours(2)->toIso8601String(),
        ],
        'group' => 'courier',
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => Http::response([
            'message' => 'Order Created Successfully',
            'type' => 'success',
            'code' => 200,
            'data' => [
                'consignment_id' => 'CONSIGN-2',
                'merchant_order_id' => 'ORD-TEST-1',
                'order_status' => 'Pending',
                'delivery_fee' => 60,
            ],
        ]),
    ]);

    (new PathaoCourierService)->createOrder($this->order);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'issue-token'));
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer cached-token'));
});

it('refreshes an expired access token using the refresh token', function () {
    Setting::create([
        'key' => 'pathao_token',
        'value' => [
            'access_token' => 'expired-token',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subHour()->toIso8601String(),
        ],
        'group' => 'courier',
    ]);

    Http::fake([
        '*/aladdin/api/v1/issue-token' => Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 432000,
            'access_token' => 'refreshed-token',
            'refresh_token' => 'new-refresh',
        ]),
        '*/aladdin/api/v1/orders' => Http::response([
            'type' => 'success',
            'data' => ['consignment_id' => 'CONSIGN-3', 'order_status' => 'Pending', 'delivery_fee' => 70],
        ]),
    ]);

    (new PathaoCourierService)->createOrder($this->order);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'issue-token')
        && ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['refresh_token'] ?? null) === 'old-refresh');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer refreshed-token'));
});

it('throws when Pathao returns a failure response', function () {
    Setting::create([
        'key' => 'pathao_token',
        'value' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
        'group' => 'courier',
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders' => Http::response(['message' => 'Invalid recipient phone', 'type' => 'error', 'code' => 422], 422),
    ]);

    expect(fn () => (new PathaoCourierService)->createOrder($this->order))
        ->toThrow(RuntimeException::class, 'Invalid recipient phone');
});

it('fetches order status info for an existing consignment', function () {
    Setting::create([
        'key' => 'pathao_token',
        'value' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
        'group' => 'courier',
    ]);

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-1/info' => Http::response([
            'type' => 'success',
            'data' => [
                'consignment_id' => 'CONSIGN-1',
                'order_status' => 'Delivered',
                'order_status_slug' => 'Delivered',
                'updated_at' => '2026-10-17 15:11:40',
            ],
        ]),
    ]);

    $info = (new PathaoCourierService)->getOrderInfo('CONSIGN-1');

    expect($info['order_status'])->toBe('Delivered');
});
