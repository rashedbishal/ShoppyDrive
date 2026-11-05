<?php

declare(strict_types=1);

/*
 * FSA-11: the lost-parcel accounting policy approved by the owner —
 * confirming a parcel lost requires existing courier evidence/verification
 * (not an unrecognized status alone) plus an authorized (mother-admin)
 * action, and must never automatically restore stock. Everything else
 * (refund eligibility, COD closure, coupon/loyalty reversal, idempotency)
 * already works through the existing, unchanged mechanisms — these tests
 * prove the one new piece (skip-restock) without touching ordinary
 * cancellation/refund behavior for any other order.
 */

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\RefundCalculator;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: Order, 1: Product}
 */
function lostParcelTestOrder(array $overrides = []): array
{
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $order = Order::create(array_merge([
        'order_number' => 'ORD-LOSTCONF-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => OrderStatus::SHIPPED,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 1060,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        'shipped_at' => now()->subDays(3),
    ], $overrides));

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 1000,
        'quantity' => 2,
        'subtotal' => 2000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    return [$order->refresh(), $product];
}

/** Links a consignment and records it as not-delivered — the existing evidence this policy requires before "confirmed lost" is accepted. */
function markOrderNotDeliveredForTest(Order $order): void
{
    makeCourierReadinessCurrent();
    linkTestConsignment($order, 'steadfast');
    verifyConsignmentForTest($order, OrderDeliveryVerification::OUTCOME_NOT_DELIVERED, 'steadfast');
}

function confirmLostPayload(array $overrides = []): array
{
    return array_merge([
        'password' => 'password',
        'note' => 'Steadfast support confirmed by phone this parcel was lost in transit on 2026-11-02, ref #SF-LOST-1.',
    ], $overrides);
}

test('the confirm-lost route carries the mother, throttle, session-blocking and web (CSRF) middleware', function () {
    $route = Route::getRoutes()->getByName('admin.orders.confirmLostParcel');
    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('admin.mother')->toContain('web')->toContain('admin')->toContain('throttle:30,15');
    expect($route->locksFor())->toBe(10);
});

test('an ordinary admin is refused, and a guest is redirected', function () {
    [$order] = lostParcelTestOrder();
    markOrderNotDeliveredForTest($order);

    actingAs(createAdmin(), 'admin')->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertForbidden();
    auth('admin')->logout();
    $this->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertRedirect();

    expect($order->refresh()->lost_confirmed_at)->toBeNull();
});

test('an incorrect password is refused', function () {
    [$order] = lostParcelTestOrder();
    markOrderNotDeliveredForTest($order);

    actingAs(createMotherAdmin(), 'admin')
        ->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload(['password' => 'wrong']))
        ->assertSessionHasErrors('password');

    expect($order->refresh()->lost_confirmed_at)->toBeNull();
});

test('an unrecognized courier status alone is not sufficient — confirmation is refused without an existing not-delivered verification', function () {
    [$order] = lostParcelTestOrder(['steadfast_status' => 'some_unmapped_status', 'steadfast_consignment_id' => 'SF-UNVERIFIED-1']);

    actingAs(createMotherAdmin(), 'admin')
        ->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())
        ->assertSessionHasErrors('verification');

    expect($order->refresh()->lost_confirmed_at)->toBeNull();
});

test('an order with no courier involvement at all cannot be confirmed lost', function () {
    [$order] = lostParcelTestOrder(['status' => OrderStatus::PROCESSING, 'shipped_at' => null]);

    actingAs(createMotherAdmin(), 'admin')
        ->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())
        ->assertSessionHasErrors('verification');

    expect($order->refresh()->lost_confirmed_at)->toBeNull();
});

test('a mother admin confirms a parcel lost once existing courier evidence already shows not-delivered', function () {
    [$order] = lostParcelTestOrder();
    markOrderNotDeliveredForTest($order);

    actingAs(createMotherAdmin(), 'admin')
        ->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->lost_confirmed_at)->not->toBeNull();
    // Status/payment are untouched — confirming lost is not itself a closure action.
    expect($order->status)->toBe(OrderStatus::SHIPPED);
    expect($order->payment_status->value)->toBe('unpaid');
    expect(OrderStatusHistory::where('order_id', $order->id)->where('notes', 'like', '%confirmed this parcel as lost%')->exists())->toBeTrue();
});

test('confirming an already-confirmed-lost order again is refused — idempotent, no duplicate effects', function () {
    [$order] = lostParcelTestOrder();
    markOrderNotDeliveredForTest($order);
    $admin = createMotherAdmin();

    actingAs($admin, 'admin')->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertSessionHas('success');
    $firstConfirmedAt = $order->refresh()->lost_confirmed_at;

    actingAs($admin, 'admin')->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertSessionHasErrors('verification');

    expect($order->refresh()->lost_confirmed_at->eq($firstConfirmedAt))->toBeTrue();
    expect(OrderStatusHistory::where('order_id', $order->id)->where('notes', 'like', '%confirmed this parcel as lost%')->count())->toBe(1);
});

test('a confirmed-lost COD parcel is cancelled without restoring stock', function () {
    // Status stays PROCESSING, not SHIPPED — FSA-11's own, separate,
    // already-approved rule refuses direct admin cancellation of a
    // shipped order regardless of this flag (by design: that status
    // requires the courier's own report instead), which is a different
    // concern from this test's actual point (skip-restock on a
    // confirmed-lost order that *can* be cancelled).
    [$order, $product] = lostParcelTestOrder(['status' => OrderStatus::PROCESSING, 'shipped_at' => null]);
    markOrderNotDeliveredForTest($order);
    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertSessionHas('success');

    $stockBefore = $product->refresh()->stock_quantity;

    actingAs(createMotherAdmin(), 'admin')->patch(route('admin.orders.cancel', $order), ['reason' => 'Confirmed lost by courier'])->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    // The one behavior this finding exists to prevent: stock must NOT come back for goods that are gone.
    expect($product->refresh()->stock_quantity)->toBe($stockBefore);
    expect($order->stock_restored_at)->toBeNull();
});

test('a confirmed-lost prepaid parcel is refunded through the existing refund workflow without restoring stock', function () {
    [$order, $product] = lostParcelTestOrder(['payment_status' => 'paid', 'payment_method' => 'mobile_banking']);
    markOrderNotDeliveredForTest($order);
    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.confirmLostParcel', $order), confirmLostPayload())->assertSessionHas('success');

    $stockBefore = $product->refresh()->stock_quantity;
    $remaining = app(RefundCalculator::class)->toTaka((int) app(RefundCalculator::class)->remainingPaisa($order->refresh()));

    actingAs(createMotherAdmin(), 'admin')
        ->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => $remaining, 'refund_method' => 'bKash'])
        ->assertSessionHas('success');

    // Shipping is never refundable (existing PAY-RETURN-001 rule, unchanged),
    // so a whole-order refund leaves payment_status at PAID, not REFUNDED —
    // the refund ledger row is the authoritative proof it was recorded.
    expect(OrderRefund::where('order_id', $order->id)->exists())->toBeTrue();
    expect($product->refresh()->stock_quantity)->toBe($stockBefore);
    expect($order->refresh()->stock_restored_at)->toBeNull();
});

test('ordinary cancellation of an order never confirmed lost still restores stock as before', function () {
    [$order, $product] = lostParcelTestOrder(['status' => OrderStatus::PROCESSING, 'shipped_at' => null]);
    $stockBefore = $product->refresh()->stock_quantity;

    actingAs(createMotherAdmin(), 'admin')->patch(route('admin.orders.cancel', $order), ['reason' => 'Customer changed mind'])->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($product->refresh()->stock_quantity)->toBe($stockBefore + 2);
    expect($order->stock_restored_at)->not->toBeNull();
});

test('ordinary refund of an order never confirmed lost still restores stock as before', function () {
    [$order, $product] = lostParcelTestOrder(['status' => OrderStatus::DELIVERED, 'payment_status' => 'paid', 'payment_method' => 'mobile_banking', 'delivered_at' => now()->subDay()]);
    makeCourierReadinessCurrent();
    linkTestConsignment($order, 'steadfast');
    verifyConsignmentForTest($order, OrderDeliveryVerification::OUTCOME_FULL_DELIVERY, 'steadfast');
    $stockBefore = $product->refresh()->stock_quantity;
    $remaining = app(RefundCalculator::class)->toTaka((int) app(RefundCalculator::class)->remainingPaisa($order->refresh()));

    actingAs(createMotherAdmin(), 'admin')
        // BIZ-012: delivered goods restock only when confirmed returned.
        ->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => $remaining, 'refund_method' => 'bKash', 'goods_returned' => true])
        ->assertSessionHas('success');

    expect(OrderRefund::where('order_id', $order->id)->exists())->toBeTrue();
    expect($product->refresh()->stock_quantity)->toBe($stockBefore + 2);
    expect($order->refresh()->stock_restored_at)->not->toBeNull();
});
