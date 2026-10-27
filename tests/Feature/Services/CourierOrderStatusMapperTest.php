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
    expect(CourierOrderStatusMapper::forSteadfast('pending_parcels'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forSteadfast('waiting_approval'))->toBe(OrderStatus::PROCESSING);
    expect(CourierOrderStatusMapper::forSteadfast('delivered_parcels'))->toBe(OrderStatus::DELIVERED);
    expect(CourierOrderStatusMapper::forSteadfast('partial_parcels'))->toBe(OrderStatus::DELIVERED);
    expect(CourierOrderStatusMapper::forSteadfast('cancelled_parcells'))->toBe(OrderStatus::CANCELLED);
    expect(CourierOrderStatusMapper::forSteadfast('unknown'))->toBeNull();
});

it('humanizes a raw status into readable text', function () {
    expect(CourierOrderStatusMapper::label('assigned_for_delivery'))->toBe('Assigned For Delivery');
    expect(CourierOrderStatusMapper::label('in_review'))->toBe('In Review');
});

it('corrects steadfast\'s "parcells" typo in the display label only', function () {
    expect(CourierOrderStatusMapper::label('cancelled_parcells'))->toBe('Cancelled Parcels');
    expect(CourierOrderStatusMapper::forSteadfast('cancelled_parcells'))->toBe(OrderStatus::CANCELLED);
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

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered_parcels');

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

it('never overwrites a cancelled order', function () {
    $order = makeMapperTestOrder(['status' => 'cancelled']);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered_parcels');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($order->steadfast_status)->toBe('delivered_parcels');
});

it('a late delivered webhook after cancellation does not resurrect the order or mark it paid', function () {
    $order = makeMapperTestOrder(['status' => 'shipped', 'payment_status' => 'unpaid']);

    // Admin cancels the order (same transition Admin\OrderController::cancel() performs).
    $order->update(['status' => OrderStatus::CANCELLED]);

    // The courier's "delivered" webhook for the original shipment arrives late.
    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered_parcels');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($order->payment_status->value)->toBe('unpaid');
    expect($order->delivered_at)->toBeNull();
    expect($order->steadfast_status)->toBe('delivered_parcels');
});

it('a late delivered webhook after cancellation writes a history note once, then is idempotent on retry', function () {
    $order = makeMapperTestOrder(['status' => 'cancelled']);
    $historyCountBefore = $order->statusHistories()->count();

    CourierOrderStatusMapper::sync($order, 'pathao', 'delivery');
    expect($order->statusHistories()->count())->toBe($historyCountBefore + 1);
    expect($order->statusHistories()->latest()->first()->notes)->toContain('already cancelled');

    // The webhook is retried with the identical raw status.
    CourierOrderStatusMapper::sync($order, 'pathao', 'delivery');
    expect($order->statusHistories()->count())->toBe($historyCountBefore + 1);
    expect($order->status)->toBe(OrderStatus::CANCELLED);
});

it('a courier-reported return still legitimately cancels an active (non-terminal) order', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    CourierOrderStatusMapper::sync($order, 'pathao', 'return_to_merchant');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
});

it('a courier that reports a return and then later reports delivered cannot flip the order back', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    // The courier itself reports the parcel coming back — a legitimate
    // return, correctly cancels the order.
    CourierOrderStatusMapper::sync($order, 'pathao', 'return_to_merchant');
    expect($order->status)->toBe(OrderStatus::CANCELLED);

    // A later, conflicting "delivered" signal for the same shipment must
    // not undo that — the order is already terminal.
    CourierOrderStatusMapper::sync($order, 'pathao', 'delivery');
    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($order->payment_status->value)->toBe('unpaid');
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
