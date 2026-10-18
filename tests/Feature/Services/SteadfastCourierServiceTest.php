<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\SteadfastCourierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::insert([
        ['key' => 'steadfast_api_key', 'value' => json_encode('api-key'), 'group' => 'courier'],
        ['key' => 'steadfast_secret_key', 'value' => json_encode('secret-key'), 'group' => 'courier'],
    ]);

    $product = Product::factory()->create();

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

it('books a Steadfast order with the recipient and COD amount', function () {
    Http::fake([
        '*/create_order' => Http::response([
            'status' => 200,
            'message' => 'Order Created Successfully',
            'consignment' => [
                'consignment_id' => 'CONSIGN-1',
                'invoice' => 'ORD-TEST-1',
                'tracking_code' => '1A2B3C',
                'status' => 'in_review',
            ],
        ]),
    ]);

    $result = (new SteadfastCourierService)->createOrder($this->order);

    expect($result['consignment_id'])->toBe('CONSIGN-1');
    expect($result['tracking_code'])->toBe('1A2B3C');
    expect($result['status'])->toBe('in_review');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/create_order')
        && $request->hasHeader('Api-Key', 'api-key')
        && $request->hasHeader('Secret-Key', 'secret-key')
        && $request['invoice'] === 'ORD-TEST-1'
        && $request['recipient_name'] === 'Jamal Uddin'
        && $request['recipient_phone'] === '01712345678'
        && $request['cod_amount'] === 1080);
});

it('sends a zero COD amount for already-paid orders', function () {
    $this->order->update(['payment_status' => 'paid']);

    Http::fake([
        '*/create_order' => Http::response([
            'status' => 200,
            'consignment' => ['consignment_id' => 'CONSIGN-2', 'status' => 'in_review'],
        ]),
    ]);

    (new SteadfastCourierService)->createOrder($this->order);

    Http::assertSent(fn ($request) => $request['cod_amount'] === 0);
});

it('throws when Steadfast returns a failure response', function () {
    Http::fake([
        '*/create_order' => Http::response(['status' => 422, 'message' => 'Invalid recipient phone'], 422),
    ]);

    expect(fn () => (new SteadfastCourierService)->createOrder($this->order))
        ->toThrow(RuntimeException::class, 'Invalid recipient phone');
});

it('throws when credentials are not configured', function () {
    Setting::query()->delete();

    expect(fn () => (new SteadfastCourierService)->createOrder($this->order))
        ->toThrow(RuntimeException::class, 'steadfast_api_key');
});

it('fetches delivery status for an existing consignment', function () {
    Http::fake([
        '*/status_by_cid/CONSIGN-1' => Http::response([
            'status' => 200,
            'delivery_status' => 'delivered',
        ]),
    ]);

    $info = (new SteadfastCourierService)->getOrderInfo('CONSIGN-1');

    expect($info['status'])->toBe('delivered');
});

it('fetches the current balance', function () {
    Http::fake([
        '*/get_balance' => Http::response([
            'status' => 200,
            'current_balance' => 1500.5,
        ]),
    ]);

    $balance = (new SteadfastCourierService)->getBalance();

    expect($balance['current_balance'])->toBe(1500.5);
});
