<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Http\Controllers\Admin\OrderController;
use App\Mail\OrderCancelledMail;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use App\Services\CourierOrderStatusMapper;
use Illuminate\Support\Facades\Mail;

/*
 * FSA-11: a SHIPPED order (only courier status sync sets it) is with the
 * courier and must not be cancelled by an admin. The courier's own
 * cancel/return report is the authoritative way to cancel it, and that path
 * reverses stock, coupon and points exactly once.
 */

beforeEach(function () {
    Mail::fake();
    $this->admin = createMotherAdmin();
    $this->customer = User::factory()->create(['points_balance' => 40]);
    $this->product = Product::factory()->create(['stock_quantity' => 10]);
    $this->coupon = Coupon::factory()->create(['used_count' => 1]);
});

/**
 * An order that already consumed 2 units of stock, one coupon use and 60
 * redeemed points (the customer's balance of 40 is what's left).
 *
 * @param  array<string, mixed>  $overrides
 */
function fsa11Order(array $overrides = []): Order
{
    $test = test();

    $order = Order::create([
        'order_number' => 'ORD-FSA11-'.uniqid(),
        'user_id' => $test->customer->id,
        'status' => OrderStatus::SHIPPED,
        'payment_status' => PaymentStatus::UNPAID,
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 60,
        'discount' => 60,
        'tax' => 0,
        'total' => 1000,
        'coupon_id' => $test->coupon->id,
        'coupon_code' => $test->coupon->code,
        'points_redeemed' => 60,
        'pathao_consignment_id' => 'PATHAO-FSA11',
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        ...$overrides,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $test->product->id,
        'product_name' => $test->product->name,
        'sku' => $test->product->sku,
        'price' => 500,
        'quantity' => 2,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $test->product->id, 'name' => $test->product->name],
    ]);

    CouponUse::create(['coupon_id' => $test->coupon->id, 'user_id' => $test->customer->id, 'order_id' => $order->id, 'discount_amount' => 60]);

    return $order;
}

/**
 * @return array{stock: int, points: int, coupon_used: int, coupon_uses: int, refund_rows: int}
 */
function fsa11Ledgers(Order $order): array
{
    $test = test();

    return [
        'stock' => $test->product->fresh()->stock_quantity,
        'points' => $test->customer->fresh()->points_balance,
        'coupon_used' => $test->coupon->fresh()->used_count,
        'coupon_uses' => CouponUse::where('order_id', $order->id)->count(),
        'refund_rows' => ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count(),
    ];
}

const FSA11_UNTOUCHED = ['stock' => 10, 'points' => 40, 'coupon_used' => 1, 'coupon_uses' => 1, 'refund_rows' => 0];
const FSA11_REVERSED_ONCE = ['stock' => 12, 'points' => 100, 'coupon_used' => 0, 'coupon_uses' => 0, 'refund_rows' => 1];

test('an admin cannot cancel a shipped order, and nothing is reversed', function (PaymentStatus $paymentStatus) {
    $order = fsa11Order(['payment_status' => $paymentStatus]);

    $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order), ['reason' => 'customer asked'])
        ->assertSessionHas('error', OrderController::SHIPPED_CANCEL_MESSAGE);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::SHIPPED)
        ->and($order->payment_status)->toBe($paymentStatus)
        ->and($order->stock_restored_at)->toBeNull()
        ->and($order->coupon_released_at)->toBeNull()
        ->and($order->cancellation_reason)->toBeNull()
        ->and(fsa11Ledgers($order))->toBe(FSA11_UNTOUCHED)
        ->and(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    Mail::assertNotQueued(OrderCancelledMail::class);
})->with([
    'unpaid COD' => [PaymentStatus::UNPAID],
    'paid' => [PaymentStatus::PAID],
]);

test('an admin can still cancel a pending or processing order, reversing everything once', function (OrderStatus $status) {
    $order = fsa11Order(['status' => $status, 'pathao_consignment_id' => null]);

    $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and(fsa11Ledgers($order))->toBe(FSA11_REVERSED_ONCE);
})->with([
    'pending' => [OrderStatus::PENDING],
    'processing' => [OrderStatus::PROCESSING],
]);

/*
 * FSA-06: a paid order (e.g. mobile banking, created paid at checkout) must
 * not be admin-cancelled directly — "Mark as Refunded" is the action that
 * actually closes a paid order out with a refund ledger entry, so this
 * admin action requires it instead of a plain cancel. (BIZ-005:
 * RefundService::assertRefundable() itself does allow refunding a
 * cancelled order that's still paid — this test is about this one admin
 * action's own guard, not that service-level rule.)
 */
test('a processing paid order cannot be cancelled, and nothing is reversed', function (OrderStatus $status) {
    $order = fsa11Order(['status' => $status, 'payment_status' => PaymentStatus::PAID, 'pathao_consignment_id' => null]);

    $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order), ['reason' => 'customer asked'])
        ->assertSessionHas('error', OrderController::PAID_CANCEL_MESSAGE);

    $order->refresh();
    expect($order->status)->toBe($status)
        ->and($order->payment_status)->toBe(PaymentStatus::PAID)
        ->and($order->stock_restored_at)->toBeNull()
        ->and($order->coupon_released_at)->toBeNull()
        ->and($order->cancellation_reason)->toBeNull()
        ->and(fsa11Ledgers($order))->toBe(FSA11_UNTOUCHED)
        ->and(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    Mail::assertNotQueued(OrderCancelledMail::class);
})->with([
    'pending' => [OrderStatus::PENDING],
    'processing' => [OrderStatus::PROCESSING],
]);

test('a delivered order is still refused', function () {
    $order = fsa11Order(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()]);

    $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('error', 'Orders that are already delivered cannot be cancelled.');

    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and(fsa11Ledgers($order))->toBe(FSA11_UNTOUCHED);
});

test('an unresolved courier submission still blocks cancelling a processing order', function () {
    $order = fsa11Order(['status' => OrderStatus::PROCESSING, 'pathao_consignment_id' => null]);
    CourierSubmission::factory()->forOrder($order)->create();

    $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and(fsa11Ledgers($order))->toBe(FSA11_UNTOUCHED);
});

test('the courier\'s return report cancels a shipped order and reverses everything once', function () {
    $order = fsa11Order();

    CourierOrderStatusMapper::sync($order, 'pathao', 'return_in_transit');
    CourierOrderStatusMapper::sync($order, 'pathao', 'returned');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and(fsa11Ledgers($order))->toBe(FSA11_REVERSED_ONCE);
});

test('a late delivered report after the courier cancelled leaves the order, stock and payment untouched but recorded', function () {
    $order = fsa11Order();
    CourierOrderStatusMapper::sync($order, 'pathao', 'returned');

    CourierOrderStatusMapper::sync($order, 'pathao', 'Delivery');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->payment_status)->toBe(PaymentStatus::UNPAID)
        ->and($order->delivered_at)->toBeNull()
        ->and(fsa11Ledgers($order))->toBe(FSA11_REVERSED_ONCE)
        ->and(OrderStatusHistory::where('order_id', $order->id)->latest('id')->value('notes'))
        ->toContain('order is already cancelled; status left unchanged');
});
