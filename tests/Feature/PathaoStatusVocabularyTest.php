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
use Illuminate\Support\Facades\Mail;

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

test('statuses needing a human stay unmapped, flagged unrecognized, and leave the order unchanged', function (string $raw) {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);
    $order->refresh();

    expect(CourierOrderStatusMapper::forPathao($raw))->toBeNull()
        ->and(CourierOrderStatusMapper::isUnrecognized('pathao', $raw))->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::SHIPPED)
        ->and($order->payment_status)->toBe(PaymentStatus::UNPAID)
        ->and($order->delivered_at)->toBeNull()
        ->and($order->stock_restored_at)->toBeNull();
})->with(['Delivery_Failed', 'Pickup_Failed', 'lost', 'damaged', 'handed_over_to_admin', 'Partial_Delivery', 'some_brand_new_status']);

test('known preserved statuses leave the order unchanged and are not flagged unrecognized (DISC-20)', function (string $raw) {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);

    CourierOrderStatusMapper::sync($order, 'pathao', $raw);
    $order->refresh();

    expect(CourierOrderStatusMapper::forPathao($raw))->toBeNull()
        ->and(CourierOrderStatusMapper::isUnrecognized('pathao', $raw))->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::SHIPPED)
        ->and($order->pathao_order_status)->toBe($raw)
        ->and($order->payment_status)->toBe(PaymentStatus::UNPAID)
        ->and($order->delivered_at)->toBeNull()
        ->and($order->stock_restored_at)->toBeNull();
})->with([
    'on_hold', 'return_on_hold', 'exchange', 'reprocess_pending', 'reprocessed', 'at_inventory',
    'Return', 'return_requested', 'return_in_transit', 'return_at_sorting', 'assigned_for_return',
    'returned', 'paid_return', 'returned_to_inventory', 'return_to_merchant', 'return_to_inventory', 'Payment_Invoice',
]);

test('the admin order page shows the Pathao courier status and when it arrived, separately from the order status (DISC-20)', function () {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);
    CourierOrderStatusMapper::sync($order, 'pathao', 'reprocess_pending');

    $this->actingAs(createAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.status', 'shipped')
            ->where('order.pathao_status_label', 'Reprocess Pending')
            ->whereNot('order.pathao_status_updated_at', null)
            ->where('order.needsCourierInvestigation', false));
});

test('a never-seen Pathao status still raises the investigation banner on an active order (DISC-20)', function () {
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);
    CourierOrderStatusMapper::sync($order, 'pathao', 'some_brand_new_status');

    $this->actingAs(createAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('order.needsCourierInvestigation', true));
});

test('a final order whose latest Pathao status maps elsewhere shows a display-only note, changing nothing (DISC-20 D1b)', function () {
    Mail::fake();
    $order = pathaoSentCodOrder(OrderStatus::SHIPPED);
    CourierOrderStatusMapper::sync($order, 'pathao', 'Delivered');
    $order->refresh();
    $before = $order->only(['status', 'payment_status', 'delivered_at', 'stock_restored_at', 'restock_skipped_at']);
    Mail::fake();

    CourierOrderStatusMapper::sync($order, 'pathao', 'pickup_cancelled');

    expect($order->refresh()->only(array_keys($before)))->toEqual($before);
    Mail::assertNothingQueued();

    $this->actingAs(createAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->where('order.status', 'delivered')
            ->where('order.courierStatusConflict', [
                'order_status' => 'delivered',
                'courier_status' => 'pickup_cancelled',
                'courier_status_label' => 'Pickup Cancelled',
                'courier_mapped_status' => 'cancelled',
            ]));
});

test('no D1b note when the statuses agree, the courier status is preserved or unmapped, or the order is not final (DISC-20)', function (OrderStatus $start, array $reports) {
    $order = pathaoSentCodOrder($start);
    foreach ($reports as $raw) {
        CourierOrderStatusMapper::sync($order->refresh(), 'pathao', $raw);
    }

    $this->actingAs(createAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('order.courierStatusConflict', null));
})->with([
    'delivered, courier agrees (alias)' => [OrderStatus::SHIPPED, ['delivery', 'Delivered']],
    'delivered, then a preserved return status' => [OrderStatus::SHIPPED, ['delivery', 'returned']],
    'delivered, then an unmapped status' => [OrderStatus::SHIPPED, ['delivery', 'lost']],
    'cancelled, courier agrees' => [OrderStatus::SHIPPED, ['pickup_cancelled']],
    'active order, mapped status differs' => [OrderStatus::SHIPPED, ['picked']],
]);
