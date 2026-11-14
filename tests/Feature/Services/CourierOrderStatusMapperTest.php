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
    expect(CourierOrderStatusMapper::forPathao('pickup_cancelled'))->toBe(OrderStatus::CANCELLED);
    // DISC-20: returns and holds preserve the order's status.
    expect(CourierOrderStatusMapper::forPathao('returned'))->toBeNull();
    expect(CourierOrderStatusMapper::forPathao('on_hold'))->toBeNull();
    expect(CourierOrderStatusMapper::forPathao('totally_unknown_status'))->toBeNull();
});

it('maps steadfast statuses to order statuses', function () {
    expect(CourierOrderStatusMapper::forSteadfast('in_review'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forSteadfast('pending'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forSteadfast('delivered'))->toBe(OrderStatus::DELIVERED);
    // DISC-03: a partial delivery is never a delivered order.
    expect(CourierOrderStatusMapper::forSteadfast('partial_delivered'))->toBeNull();
    expect(CourierOrderStatusMapper::forPathao('partial_delivery'))->toBeNull();
    expect(CourierOrderStatusMapper::forSteadfast('cancelled'))->toBe(OrderStatus::CANCELLED);
    expect(CourierOrderStatusMapper::forSteadfast('unknown'))->toBeNull();
});

it('maps the steadfast parcel status names alongside the api spellings', function () {
    expect(CourierOrderStatusMapper::forSteadfast('pending_parcels'))->toBe(OrderStatus::PENDING);
    expect(CourierOrderStatusMapper::forSteadfast('delivered_parcels'))->toBe(OrderStatus::DELIVERED);
    expect(CourierOrderStatusMapper::forSteadfast('Cancelled Parcels'))->toBe(OrderStatus::CANCELLED);
    expect(CourierOrderStatusMapper::forSteadfast('waiting_approval'))->toBeNull();
});

it('treats steadfast waiting_approval as a known status, not an unrecognized one', function () {
    expect(CourierOrderStatusMapper::isUnrecognized('steadfast', 'waiting_approval'))->toBeFalse();
    expect(CourierOrderStatusMapper::isUnrecognized('steadfast', 'delivered_parcels'))->toBeFalse();
    expect(CourierOrderStatusMapper::isUnrecognized('steadfast', 'partial_delivered'))->toBeTrue();
    expect(CourierOrderStatusMapper::isUnrecognized('steadfast', 'delivered_approval_pending'))->toBeTrue();
});

it('leaves the order status unchanged when steadfast reports waiting_approval', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'waiting_approval');

    expect($order->status)->toBe(OrderStatus::SHIPPED);
    expect($order->steadfast_status)->toBe('waiting_approval');
});

it('settles a delivered_parcels report like delivered', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered_parcels');

    expect($order->status)->toBe(OrderStatus::DELIVERED);
    expect($order->payment_status)->toBe(PaymentStatus::PAID);
});

it('humanizes a raw status into readable text', function () {
    expect(CourierOrderStatusMapper::label('assigned_for_delivery'))->toBe('Assigned For Delivery');
    expect(CourierOrderStatusMapper::label('in_review'))->toBe('In Review');
});

it('never settles an order on an approval-pending or needs-attention steadfast status', function (string $status) {
    expect(CourierOrderStatusMapper::forSteadfast($status))->toBeNull();
})->with(['delivered_approval_pending', 'partial_delivered_approval_pending', 'cancelled_approval_pending', 'unknown_approval_pending', 'hold', 'exceptional']);

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

it('never overwrites a cancelled order', function () {
    $order = makeMapperTestOrder(['status' => 'cancelled']);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($order->steadfast_status)->toBe('delivered');
});

it('never regresses a delivered order back to an earlier status', function () {
    // SHIP-001 regression: a late/out-of-order/corrective courier report
    // must not demote an already-delivered order, the same way it can't
    // resurrect a cancelled one.
    $order = makeMapperTestOrder(['status' => 'delivered', 'payment_status' => 'paid']);
    $order->forceFill(['delivered_at' => now()->subDay()])->save();

    CourierOrderStatusMapper::sync($order, 'steadfast', 'pending');

    expect($order->status)->toBe(OrderStatus::DELIVERED);
    expect($order->payment_status->value)->toBe('paid');
    expect($order->steadfast_status)->toBe('pending');
    expect($order->statusHistories()->latest()->first()->notes)->toContain('already delivered');
});

it('a late delivered webhook after cancellation does not resurrect the order or mark it paid', function () {
    $order = makeMapperTestOrder(['status' => 'shipped', 'payment_status' => 'unpaid']);

    // Admin cancels the order (same transition Admin\OrderController::cancel() performs).
    $order->update(['status' => OrderStatus::CANCELLED]);

    // The courier's "delivered" webhook for the original shipment arrives late.
    CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($order->payment_status->value)->toBe('unpaid');
    expect($order->delivered_at)->toBeNull();
    expect($order->steadfast_status)->toBe('delivered');
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

it('a courier-reported cancellation still legitimately cancels an active (non-terminal) order', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    CourierOrderStatusMapper::sync($order, 'pathao', 'pickup_cancelled');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
});

it('a courier that reports a cancellation and then later reports delivered cannot flip the order back', function () {
    $order = makeMapperTestOrder(['status' => 'shipped']);

    // The courier itself reports the parcel cancelled (DISC-20: the status
    // Pathao sends after a dashboard cancellation), which cancels the order.
    CourierOrderStatusMapper::sync($order, 'pathao', 'pickup_cancelled');
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

// MED-02: Pathao is polled, so each report is its current state rather than a
// delayed event, and it can legitimately step back from shipped.
it('lets pathao report a real, explicitly mapped step back from shipped', function (string $raw, OrderStatus $expected) {
    $order = makeMapperTestOrder(['status' => 'shipped', 'pathao_order_status' => 'in_transit']);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);

    expect($order->refresh()->status)->toBe($expected);
})->with([
    'back at the sorting hub' => ['at_sorting_hub', OrderStatus::PROCESSING],
    'picked again' => ['picked', OrderStatus::PROCESSING],
]);

// DISC-20 (owner decision C): these are recorded and shown, never applied.
it('keeps a shipped order shipped for holds and reprocessing', function (string $raw) {
    $order = makeMapperTestOrder(['status' => 'shipped', 'pathao_order_status' => 'in_transit']);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);

    expect($order->refresh()->status)->toBe(OrderStatus::SHIPPED)
        ->and($order->pathao_order_status)->toBe($raw);
})->with(['on_hold', 'reprocess_pending', 'reprocessed', 'at_inventory', 'exchange', 'return_on_hold']);

it('lets pathao return an on-hold order to shipped and then delivered', function () {
    $order = makeMapperTestOrder(['status' => 'shipped', 'pathao_order_status' => 'in_transit']);

    CourierOrderStatusMapper::sync($order, 'pathao', 'on_hold');
    CourierOrderStatusMapper::sync($order, 'pathao', 'assigned_for_delivery');
    CourierOrderStatusMapper::sync($order, 'pathao', 'delivery');

    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED);
});

it('never lets a steadfast report step back, unlike pathao', function () {
    $order = makeMapperTestOrder(['status' => 'shipped', 'steadfast_status' => 'unknown']);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'in_review');

    expect($order->refresh()->status)->toBe(OrderStatus::SHIPPED);
    expect($order->steadfast_status)->toBe('in_review');
});
