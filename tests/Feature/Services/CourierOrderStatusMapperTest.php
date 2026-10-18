<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CourierOrderStatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMapperTestOrder(array $overrides = []): Order
{
    $product = Product::factory()->create();

    $order = Order::create(array_merge([
        'order_number' => 'ORD-MAPPER-1',
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

it('maps pathao slugs to order statuses', function () {
    expect(CourierOrderStatusMapper::forPathao('pending'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forPathao('at_sorting_hub'))->toBe(OrderStatus::PROCESSING);
    expect(CourierOrderStatusMapper::forPathao('assigned_for_delivery'))->toBe(OrderStatus::SHIPPED);
    expect(CourierOrderStatusMapper::forPathao('delivery'))->toBe(OrderStatus::DELIVERED);
    expect(CourierOrderStatusMapper::forPathao('returned'))->toBe(OrderStatus::CANCELLED);
    expect(CourierOrderStatusMapper::forPathao('on_hold'))->toBe(OrderStatus::PROCESSING);
    expect(CourierOrderStatusMapper::forPathao('totally_unknown_status'))->toBeNull();
});

it('maps steadfast statuses to order statuses', function () {
    expect(CourierOrderStatusMapper::forSteadfast('in_review'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forSteadfast('delivered'))->toBe(OrderStatus::DELIVERED);
    expect(CourierOrderStatusMapper::forSteadfast('cancelled'))->toBe(OrderStatus::CANCELLED);
    expect(CourierOrderStatusMapper::forSteadfast('exceptional'))->toBe(OrderStatus::PROCESSING);
    expect(CourierOrderStatusMapper::forSteadfast('unknown'))->toBeNull();
});

it('humanizes a raw status into readable text', function () {
    expect(CourierOrderStatusMapper::label('assigned_for_delivery'))->toBe('Assigned For Delivery');
    expect(CourierOrderStatusMapper::label('in_review'))->toBe('In Review');
});

it('advances the order status when the mapped status differs', function () {
    $order = makeMapperTestOrder();

    CourierOrderStatusMapper::sync($order, 'pathao', 'in_transit');

    expect($order->status)->toBe(OrderStatus::SHIPPED);
    expect($order->pathao_order_status)->toBe('in_transit');
    expect($order->statusHistories()->latest()->first()->notes)->toContain('In Transit');
});

it('sets delivered_at and payment_status when mapped to delivered', function () {
    $order = makeMapperTestOrder();

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered');

    expect($order->status)->toBe(OrderStatus::DELIVERED);
    expect($order->payment_status)->toBe(PaymentStatus::PAID);
    expect($order->delivered_at)->not->toBeNull();
});

it('never overwrites a refunded order', function () {
    $order = makeMapperTestOrder(['status' => 'refunded']);

    CourierOrderStatusMapper::sync($order, 'pathao', 'returned');

    expect($order->status)->toBe(OrderStatus::REFUNDED);
    expect($order->pathao_order_status)->toBe('returned');
});

it('does nothing when the raw status is unchanged (idempotent retries)', function () {
    $order = makeMapperTestOrder();
    $order->update(['pathao_order_status' => 'in_transit', 'status' => 'shipped']);
    $historyCountBefore = $order->statusHistories()->count();

    CourierOrderStatusMapper::sync($order, 'pathao', 'in_transit');

    expect($order->statusHistories()->count())->toBe($historyCountBefore);
});

it('does not change order status for an unrecognized raw status', function () {
    $order = makeMapperTestOrder();

    CourierOrderStatusMapper::sync($order, 'pathao', 'some_brand_new_status');

    expect($order->status)->toBe(OrderStatus::PENDING);
    expect($order->pathao_order_status)->toBe('some_brand_new_status');
});
