<?php

declare(strict_types=1);

use App\Enums\CourierEvidenceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CourierDeliveryEvidence;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierSubmissionService;

/**
 * DISC-20: Pathao reports its own spellings (`Delivered`, `Picked`,
 * `At_the_Sorting_HUB`, …), which the original map never contained.
 */
function pathaoSentCodOrder(OrderStatus $status): Order
{
    $order = Order::create([
        'order_number' => 'ORD-PV-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => OrderStatus::PENDING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'T', 'phone' => '01700000000'],
        'billing_address' => [],
    ]);
    $submission = CourierSubmission::factory()->forOrder($order)->create(['courier' => 'pathao']);
    app(CourierSubmissionService::class)->markSent($submission, ['consignment_id' => "PT-{$order->id}", 'tracking_code' => 'T1']);

    $order->refresh();
    $order->update(['status' => $status]);

    return $order->refresh();
}

test('Pathao\'s own spellings map to the expected order statuses', function (string $raw, OrderStatus $expected) {
    expect(CourierOrderStatusMapper::forPathao($raw))->toBe($expected)
        ->and(CourierOrderStatusMapper::isUnrecognized('pathao', $raw))->toBeFalse();
})->with([
    ['Delivered', OrderStatus::DELIVERED],
    ['delivered', OrderStatus::DELIVERED],
    ['Picked', OrderStatus::PROCESSING],
    ['At_the_Sorting_HUB', OrderStatus::PROCESSING],
    ['Received_at_Last_Mile_HUB', OrderStatus::SHIPPED],
    ['Pickup_Requested', OrderStatus::PENDING],
    ['Assigned_for_Pickup', OrderStatus::PENDING],
]);

test('the keys that existed before DISC-20 still map as before', function (string $raw, OrderStatus $expected) {
    expect(CourierOrderStatusMapper::forPathao($raw))->toBe($expected);
})->with([
    ['delivery', OrderStatus::DELIVERED],
    ['at_sorting_hub', OrderStatus::PROCESSING],
    ['at_delivery_hub', OrderStatus::SHIPPED],
    ['In_Transit', OrderStatus::SHIPPED],
    ['Pickup_Cancelled', OrderStatus::CANCELLED],
]);

test('Pathao "Delivered" on a shipped COD order marks it delivered and paid, with recognized evidence', function () {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'pathao', 'Delivered');
    $order->refresh();
    $evidence = CourierDeliveryEvidence::where('order_id', $order->id)
        ->where('evidence_type', CourierEvidenceType::TRANSITION)
        ->sole();

    expect($order->status)->toBe(OrderStatus::DELIVERED)
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::PAID)
        ->and($order->pathao_order_status)->toBe('Delivered')
        ->and($evidence->raw_status)->toBe('Delivered')
        ->and($evidence->mapped_order_status)->toBe('delivered')
        ->and($evidence->order_status_after)->toBe('delivered')
        ->and(CourierOrderStatusMapper::isUnrecognized('pathao', $order->pathao_order_status))->toBeFalse();
});

test('Pathao progress statuses move a pending order forward', function (string $raw, OrderStatus $expected) {
    $order = pathaoSentCodOrder(OrderStatus::PENDING);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);

    expect($order->refresh()->status)->toBe($expected)
        ->and($order->pathao_order_status)->toBe($raw);
})->with([
    ['Picked', OrderStatus::PROCESSING],
    ['At_the_Sorting_HUB', OrderStatus::PROCESSING],
    ['Received_at_Last_Mile_HUB', OrderStatus::SHIPPED],
]);

/*
 * Transition protection (unchanged by DISC-20). For Pathao, sync() protects
 * only the final statuses; its forward-only guard applies to Steadfast alone
 * (CourierOrderStatusMapper::sync(), `$provider === 'steadfast' &&
 * movesBackward()`). Recorded as a known, pre-existing limitation.
 */
test('documented limitation: Pathao has no forward-only guard, so an earlier status moves a shipped order back', function (string $raw, OrderStatus $movedTo) {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);

    expect($order->refresh()->status)->toBe($movedTo)
        ->and($order->pathao_order_status)->toBe($raw)
        ->and($order->payment_status)->toBe(PaymentStatus::UNPAID)
        ->and($order->delivered_at)->toBeNull();
})->with([
    ['Picked', OrderStatus::PROCESSING],
    ['At_the_Sorting_HUB', OrderStatus::PROCESSING],
    ['Pickup_Requested', OrderStatus::PENDING],
    ['Assigned_for_Pickup', OrderStatus::PENDING],
]);

test('Steadfast keeps its forward-only guard (the contrast to the Pathao limitation)', function () {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'steadfast', 'pending');

    expect($order->refresh()->status)->toBe(OrderStatus::SHIPPED);
});

test('a final status is never moved by any later Pathao report', function (OrderStatus $final, string $raw) {
    $order = pathaoSentCodOrder($final);
    $paymentBefore = $order->payment_status;

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);

    expect($order->refresh()->status)->toBe($final)
        ->and($order->payment_status)->toBe($paymentBefore)
        ->and($order->pathao_order_status)->toBe($raw);
})->with([
    'delivered, then a progress status' => [OrderStatus::DELIVERED, 'Received_at_Last_Mile_HUB'],
    'delivered, then pickup requested' => [OrderStatus::DELIVERED, 'Pickup_Requested'],
    'cancelled, then delivered' => [OrderStatus::CANCELLED, 'Delivered'],
    'cancelled, then picked' => [OrderStatus::CANCELLED, 'Picked'],
    'refunded, then delivered' => [OrderStatus::REFUNDED, 'Delivered'],
    'refunded, then sorting hub' => [OrderStatus::REFUNDED, 'At_the_Sorting_HUB'],
]);

test('an order delivered through Pathao "Delivered" stays delivered and paid on a later report', function () {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);
    CourierOrderStatusMapper::sync($order, 'pathao', 'Delivered');

    CourierOrderStatusMapper::sync($order->refresh(), 'pathao', 'Received_at_Last_Mile_HUB');

    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and($order->payment_status)->toBe(PaymentStatus::PAID);
});

test('statuses deliberately left unmapped stay unrecognized and leave the order unchanged', function (string $raw) {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);
    $order->refresh();

    expect(CourierOrderStatusMapper::forPathao($raw))->toBeNull()
        ->and(CourierOrderStatusMapper::isUnrecognized('pathao', $raw))->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::SHIPPED)
        ->and($order->payment_status)->toBe(PaymentStatus::UNPAID)
        ->and($order->delivered_at)->toBeNull()
        ->and($order->stock_restored_at)->toBeNull();
})->with(['Return', 'Delivery_Failed', 'Pickup_Failed', 'Payment_Invoice']);
