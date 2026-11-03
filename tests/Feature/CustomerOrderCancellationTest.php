<?php

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
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
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

/*
 * FSA-15: a PENDING order can already have a courier consignment —
 * CourierSubmissionService::claim()/markSent() never touch `status`, only
 * the consignment columns, so the order stays `pending` until the async
 * courier-status sync later promotes it to `shipped`. The customer
 * self-service cancel route (routes/account.php) must refuse to cancel
 * once any courier involvement is detected, the same way
 * Admin\OrderController::cancel() already refuses a SHIPPED order.
 */

beforeEach(function () {
    Mail::fake();
    $this->customer = User::factory()->create(['points_balance' => 40]);
    $this->otherCustomer = User::factory()->create();
    $this->product = Product::factory()->create(['stock_quantity' => 10]);
    $this->coupon = Coupon::factory()->create(['used_count' => 1]);
});

/**
 * A pending order that already consumed 2 units of stock, one coupon use
 * and 60 redeemed points (the customer's balance of 40 is what's left) —
 * same ledger shape as ShippedOrderCancellationTest's fsa11Order(), just
 * PENDING instead of SHIPPED, since that's the only status this route ever
 * acts on.
 *
 * @param  array<string, mixed>  $overrides
 */
function fsa15Order(array $overrides = []): Order
{
    $test = test();

    $order = Order::create([
        'order_number' => 'ORD-FSA15-'.uniqid(),
        'user_id' => $test->customer->id,
        'status' => OrderStatus::PENDING,
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
function fsa15Ledgers(Order $order): array
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

const FSA15_UNTOUCHED = ['stock' => 10, 'points' => 40, 'coupon_used' => 1, 'coupon_uses' => 1, 'refund_rows' => 0];
const FSA15_REVERSED_ONCE = ['stock' => 12, 'points' => 100, 'coupon_used' => 0, 'coupon_uses' => 0, 'refund_rows' => 1];

test('a customer can cancel a genuinely pending order with no courier involvement', function () {
    $order = fsa15Order();

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('success', 'Your order has been cancelled.');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    expect(fsa15Ledgers($order))->toBe(FSA15_REVERSED_ONCE);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    Mail::assertQueued(OrderCancelledMail::class, 1);
});

test('a customer cannot cancel a pending order with a resolved Pathao consignment', function () {
    $order = fsa15Order(['pathao_consignment_id' => 'PATHAO-FSA15']);

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    Mail::assertNotQueued(OrderCancelledMail::class);
});

test('a customer cannot cancel a pending order with a resolved Steadfast consignment', function () {
    $order = fsa15Order(['steadfast_consignment_id' => 'SF-FSA15']);

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
});

test('a customer cannot cancel while an unresolved SUBMITTING courier submission exists', function () {
    $order = fsa15Order();
    CourierSubmission::factory()->forOrder($order)->create(['state' => CourierSubmissionState::SUBMITTING]);

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
});

test('a customer cannot cancel while an unresolved UNKNOWN courier submission exists', function () {
    $order = fsa15Order();
    CourierSubmission::factory()->forOrder($order)->unknown()->create();

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'This order is already being processed by our courier and can no longer be cancelled here. Please contact our support team for assistance.');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
});

test('a resolved (SENT) courier submission with no active claim does not itself block cancellation beyond the consignment-column check', function () {
    // SENT clears active_order_id, so hasUnresolvedSubmission() alone would
    // miss this — the consignment-column check is what actually catches it.
    $order = fsa15Order(['pathao_consignment_id' => 'PATHAO-FSA15']);
    CourierSubmission::factory()->forOrder($order)->create([
        'state' => CourierSubmissionState::SENT,
        'active_order_id' => null,
        'consignment_id' => 'PATHAO-FSA15',
        'finished_at' => now(),
    ]);

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
});

test('a different customer cannot cancel another customer\'s order', function () {
    $order = fsa15Order();

    actingAs($this->otherCustomer)
        ->patch(route('account.orders.cancel', $order))
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::PENDING);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
});

test('non-pending orders remain non-cancellable', function (OrderStatus $status) {
    $order = fsa15Order(['status' => $status]);

    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'Only pending orders can be cancelled.');

    expect($order->refresh()->status)->toBe($status);
    expect(fsa15Ledgers($order))->toBe(FSA15_UNTOUCHED);
})->with([
    'processing' => [OrderStatus::PROCESSING],
    'shipped' => [OrderStatus::SHIPPED],
    'delivered' => [OrderStatus::DELIVERED],
    'cancelled' => [OrderStatus::CANCELLED],
    'refunded' => [OrderStatus::REFUNDED],
]);

test('a second cancel submitted against an already-cancelled order changes nothing further', function () {
    $order = fsa15Order();

    actingAs($this->customer)->patch(route('account.orders.cancel', $order))->assertSessionHas('success');
    actingAs($this->customer)
        ->patch(route('account.orders.cancel', $order))
        ->assertSessionHas('error', 'Only pending orders can be cancelled.');

    expect(fsa15Ledgers($order))->toBe(FSA15_REVERSED_ONCE);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    Mail::assertQueued(OrderCancelledMail::class, 1);
});
