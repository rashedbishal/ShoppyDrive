<?php

declare(strict_types=1);

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Enums\ReturnStatus;
use App\Exceptions\CouldNotRecordRefundException;
use App\Models\CourierSubmission;
use App\Models\CourierVerificationReadiness;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\CourierEvidenceChain;
use App\Services\LoyaltyPointsService;
use App\Services\RefundCalculator;
use App\Services\RefundService;
use Illuminate\Database\QueryException;

use function Pest\Laravel\actingAs;

/**
 * PAY-RETURN-001 order builder: lines are [unit price, quantity].
 *
 * @param  array<string, mixed>  $overrides
 * @param  list<array{0: float|int, 1: int}>  $lines
 */
function refundOrder(array $overrides = [], array $lines = [[600, 1], [400, 1]], ?User $user = null): Order
{
    $subtotal = array_sum(array_map(fn (array $l) => $l[0] * $l[1], $lines));
    $discount = $overrides['discount'] ?? 0;
    $shipping = $overrides['shipping_cost'] ?? 0;

    $order = Order::create(array_merge([
        'order_number' => 'ORD-PR-'.strtoupper(bin2hex(random_bytes(4))),
        'user_id' => ($user ?? User::factory()->create())->id,
        'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid',
        'payment_method' => 'zinipay',
        'subtotal' => $subtotal,
        'shipping_cost' => $shipping,
        'discount' => $discount,
        'tax' => 0,
        'total' => max(0, $subtotal - $discount + $shipping),
        'shipping_address' => ['name' => 'T', 'phone' => '01700000000'],
        'billing_address' => [],
        'delivered_at' => now()->subDays(8),
    ], $overrides));

    foreach ($lines as [$price, $quantity]) {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 50]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'price' => $price, 'quantity' => $quantity, 'subtotal' => $price * $quantity, 'product_snapshot' => [],
        ]);
    }

    return $order->refresh();
}

/** @param  array<int, int>  $quantities  item index => quantity */
function approvedReturn(Order $order, array $quantities): ReturnRequest
{
    $items = $order->items()->orderBy('id')->get();

    return ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $order->user_id, 'reason' => 'Return', 'status' => ReturnStatus::APPROVED,
        'items' => collect($quantities)->map(fn (int $q, int $i) => ['order_item_id' => $items[$i]->id, 'product_id' => $items[$i]->product_id, 'quantity' => $q])->values()->all(),
    ]);
}

// ── Calculation ─────────────────────────────────────────────────────────────

test('the order discount is shared across lines by largest remainder and sums exactly', function () {
    $order = refundOrder(['discount' => 100], [[333.33, 1], [333.33, 1], [333.34, 1]]);
    $lines = app(RefundCalculator::class)->lineNetPaisa($order);

    expect(array_sum($lines))->toBe(99_999 + 1 - 10_000)
        ->and(array_values($lines))->each->toBeGreaterThan(29_000);
});

test('successive partial returns of one line sum exactly to its net value, never more', function () {
    $calculator = app(RefundCalculator::class);
    $net = 89_999; // 999.99 line with a 100.00 discount

    $values = [];
    $before = 0;

    foreach ([1, 1, 1] as $units) {
        $values[] = $calculator->unitsValuePaisa($net, 3, $before + $units) - $calculator->unitsValuePaisa($net, 3, $before);
        $before += $units;
    }

    expect($values)->toBe([29_999, 30_000, 30_000])->and(array_sum($values))->toBe($net);
});

test('cumulative refundable value never exceeds the cap for any split of returns', function (array $lines, int $discount, array $splits) {
    $order = refundOrder(['discount' => $discount, 'shipping_cost' => 60], $lines);
    $calculator = app(RefundCalculator::class);
    $total = 0;

    foreach ($splits as $split) {
        $returnRequest = approvedReturn($order, $split);
        $value = $calculator->returnValuePaisa($order, $returnRequest);
        $returnRequest->update(['status' => ReturnStatus::REFUNDED]);
        $total += $value;
    }

    expect($total)->toBeLessThanOrEqual($calculator->capPaisa($order));
})->with([
    'odd split, big discount' => [[[333.33, 3], [10, 7]], 77, [[0 => 1], [0 => 1, 1 => 3], [0 => 1, 1 => 4]]],
    'tiny lines' => [[[0.01, 3], [0.02, 1]], 0, [[0 => 1], [0 => 2, 1 => 1]]],
    'discount larger than line' => [[[50, 1], [950, 1]], 999, [[0 => 1], [1 => 1]]],
]);

test('the cap excludes shipping and is the product net of what was paid', function () {
    $order = refundOrder(['discount' => 100, 'shipping_cost' => 60]); // total 960

    $calculator = app(RefundCalculator::class);
    expect($calculator->collectedPaisa($order))->toBe(96_000)
        ->and($calculator->productNetPaisa($order))->toBe(90_000)
        ->and($calculator->capPaisa($order))->toBe(90_000);
});

test('COD collection uses the whole-taka amount the courier collected, in both rounding directions', function (float $subtotal, float $discount, int $collected, int $cap) {
    $order = refundOrder(['payment_method' => 'cod', 'discount' => $discount, 'shipping_cost' => 60], [[$subtotal, 1]]);
    $calculator = app(RefundCalculator::class);

    expect($calculator->collectedPaisa($order))->toBe($collected)->and($calculator->capPaisa($order))->toBe($cap);
})->with([
    'rounds up (959.55 -> 960)' => [999.50, 99.95, 96_000, 89_955],
    'rounds down (959.45 -> 959)' => [999.40, 99.95, 95_900, 89_900],
]);

test('collected amount: online uses paid payment rows, admin-paid uses the total, unpaid is zero, undelivered COD is unknown', function () {
    $calculator = app(RefundCalculator::class);

    $online = refundOrder(['shipping_cost' => 60]);
    Payment::create(['order_id' => $online->id, 'method' => 'zinipay', 'amount' => 900, 'status' => PaymentStatus::PAID]);
    expect($calculator->collectedPaisa($online))->toBe(90_000);

    expect($calculator->collectedPaisa(refundOrder(['payment_method' => 'mobile_banking'])))->toBe(100_000)
        ->and($calculator->collectedPaisa(refundOrder(['payment_status' => 'unpaid'])))->toBe(0)
        ->and($calculator->collectedPaisa(refundOrder(['payment_method' => 'cod', 'delivered_at' => null, 'status' => OrderStatus::SHIPPED])))->toBeNull();
});

// ── Shared ledger, through the real admin routes ────────────────────────────

test('a return refund then a whole-order refund share one ledger and never exceed the cap', function () {
    $admin = createAdmin();
    $order = refundOrder(['discount' => 100, 'shipping_cost' => 60]); // cap 900, nets 540 / 360
    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [1 => 1]);

    actingAs($admin, 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 360.01, 'refund_method' => 'bKash'])->assertSessionHas('error');
    actingAs($admin, 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 300, 'refund_method' => 'bKash'])->assertSessionHas('success');
    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED);

    actingAs($admin, 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 600, 'refund_method' => 'bank'])->assertSessionHas('success');

    $order->refresh();
    expect((float) OrderRefund::where('order_id', $order->id)->sum('amount'))->toBe(900.0)
        ->and($order->status)->toBe(OrderStatus::REFUNDED)
        // BIZ-008: payment is "fully refunded" once the refundable cap (900,
        // product value net of discount) is reached — shipping is never
        // refundable, so the cap itself already excludes it.
        ->and($order->payment_status)->toBe(PaymentStatus::REFUNDED);
});

test('no return refund can be recorded once the order was refunded as a whole', function () {
    $order = refundOrder();
    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [0 => 1]);
    $order->forceFill(['status' => OrderStatus::REFUNDED])->saveQuietly();

    expect(fn () => app(RefundService::class)->recordReturnRefund(createAdmin(), $returnRequest, 100, 'bKash'))
        ->toThrow(CouldNotRecordRefundException::class);
    expect(OrderRefund::count())->toBe(0);
});

test('a whole-order refund is refused while a return is pending or approved', function (ReturnStatus $status) {
    $order = refundOrder();
    courierVerifiedForRefund($order);
    approvedReturn($order, [0 => 1])->update(['status' => $status]);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('error');
    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED);
})->with([ReturnStatus::PENDING, ReturnStatus::APPROVED]);

test('payment becomes REFUNDED only when everything collected was refunded, and only then the payment rows', function () {
    $order = refundOrder(); // free shipping, total 1000
    Payment::create(['order_id' => $order->id, 'method' => 'zinipay', 'amount' => 1000, 'status' => PaymentStatus::PAID]);
    courierVerifiedForRefund($order);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'gateway'])->assertSessionHas('success');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::REFUNDED)
        ->and(Payment::where('order_id', $order->id)->value('status'))->toBe(PaymentStatus::REFUNDED)
        ->and($order->statusHistories()->where('status', 'refunded')->exists())->toBeTrue();
});

// ── BIZ-005: a cancelled order can still be refunded if it was paid ────────

test('a cancelled order that was paid can still be refunded', function () {
    $order = refundOrder(['status' => OrderStatus::CANCELLED]);
    courierVerifiedForRefund($order);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), wholeOrderRefundPayload($order))->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and(OrderRefund::sole()->resulting_order_status)->toBe('refunded');
});

test('a cancelled order that was never paid still cannot be refunded', function () {
    $order = refundOrder(['status' => OrderStatus::CANCELLED, 'payment_status' => PaymentStatus::UNPAID]);
    courierVerifiedForRefund($order);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and(OrderRefund::count())->toBe(0);
});

test('a full return through the admin route writes the order status history and the ledger row', function () {
    $admin = createAdmin();
    $order = refundOrder(['shipping_cost' => 60]);
    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [0 => 1, 1 => 1]);

    actingAs($admin, 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('success');

    $refund = OrderRefund::sole();
    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED)
        // BIZ-008: a full return refunds the whole product cap, so payment
        // flips to REFUNDED too (shipping is never part of the cap).
        ->and($order->payment_status)->toBe(PaymentStatus::REFUNDED)
        ->and($refund->return_request_id)->toBe($returnRequest->id)
        ->and($refund->admin_id)->toBe($admin->id)
        ->and($refund->resulting_order_status)->toBe('refunded')
        ->and($order->statusHistories()->where('notes', 'like', "%Return request #{$returnRequest->id}%")->exists())->toBeTrue();
});

test('a refunded return without a ledger amount blocks further refunds instead of guessing', function () {
    $order = refundOrder();
    courierVerifiedForRefund($order);
    ReturnRequest::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'reason' => 'old', 'status' => ReturnStatus::REFUNDED, 'items' => []]);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('error');
    expect(OrderRefund::count())->toBe(0);
});

test('a second identical whole-order refund is refused and the ledger keeps one row', function () {
    $order = refundOrder();
    courierVerifiedForRefund($order);
    $admin = createAdmin();

    actingAs($admin, 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('success');
    actingAs($admin, 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('error');

    expect(OrderRefund::count())->toBe(1);
});

test('the database refuses two ledger rows for the same return request', function () {
    $order = refundOrder();
    $returnRequest = approvedReturn($order, [0 => 1]);
    OrderRefund::factory()->create(['order_id' => $order->id, 'return_request_id' => $returnRequest->id]);

    expect(fn () => OrderRefund::factory()->create(['order_id' => $order->id, 'return_request_id' => $returnRequest->id]))->toThrow(QueryException::class);
});

// ── No-courier path vs. fail-closed courier involvement ─────────────────────

test('only PENDING and PROCESSING orders with no courier signal at all skip courier verification', function (OrderStatus $status) {
    $order = refundOrder(['status' => $status, 'delivered_at' => null]);

    $eligibility = app(CourierEvidenceChain::class)->eligibility($order);

    expect($eligibility['eligible'])->toBe(in_array($status, [OrderStatus::PENDING, OrderStatus::PROCESSING], true))
        ->and($eligibility['courier_involved'])->toBe(! in_array($status, [OrderStatus::PENDING, OrderStatus::PROCESSING], true));
})->with(OrderStatus::cases());

test('the no-courier path needs no readiness attestation', function () {
    $order = refundOrder(['status' => OrderStatus::PROCESSING, 'delivered_at' => null]);

    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'gateway'])->assertSessionHas('success');
    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
});

test('a PROCESSING order with any courier signal fails closed', function (Closure $signal) {
    $order = refundOrder(['status' => OrderStatus::PROCESSING, 'delivered_at' => null]);
    $signal($order);

    expect(app(CourierEvidenceChain::class)->eligibility($order->refresh())['eligible'])->toBeFalse();
    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'gateway'])->assertSessionHas('error');
})->with([
    'a failed courier submission' => [fn (Order $o) => CourierSubmission::factory()->forOrder($o)->create(['state' => CourierSubmissionState::FAILED, 'active_order_id' => null])],
    'a sent courier submission' => [fn (Order $o) => CourierSubmission::factory()->forOrder($o)->create(['state' => CourierSubmissionState::SENT, 'active_order_id' => null, 'consignment_id' => 'X1'])],
    'a raw courier status' => [fn (Order $o) => $o->forceFill(['steadfast_status' => 'in_review'])->save()],
    'shipped_at' => [fn (Order $o) => $o->forceFill(['shipped_at' => now()])->save()],
    'delivered_at' => [fn (Order $o) => $o->forceFill(['delivered_at' => now()])->save()],
]);

test('a SHIPPED order with no consignment can never be refunded, even with readiness', function () {
    makeCourierReadinessCurrent();
    $order = refundOrder(['status' => OrderStatus::SHIPPED, 'delivered_at' => null]);

    $eligibility = app(CourierEvidenceChain::class)->eligibility($order);
    expect($eligibility['eligible'])->toBeFalse()
        ->and(implode(' ', $eligibility['reasons']))->toContain('without a linked courier consignment');
});

test('a courier-handled order is not refundable without a current readiness attestation', function () {
    $order = refundOrder();
    linkTestConsignment($order);

    $eligibility = app(CourierEvidenceChain::class)->eligibility($order->refresh());
    expect($eligibility['eligible'])->toBeFalse()
        ->and(implode(' ', $eligibility['reasons']))->toContain('readiness');
});

test('a failed submission becomes refundable only with a current not-created verification', function () {
    makeCourierReadinessCurrent();
    $order = refundOrder(['status' => OrderStatus::PROCESSING, 'delivered_at' => null]);
    $submission = CourierSubmission::factory()->forOrder($order)->create(['state' => CourierSubmissionState::FAILED, 'active_order_id' => null]);
    $chain = app(CourierEvidenceChain::class);
    expect($chain->eligibility($order)['eligible'])->toBeFalse();

    OrderDeliveryVerification::factory()->create([
        'order_id' => $order->id, 'target_type' => 'submission', 'provider' => 'steadfast', 'courier_submission_id' => $submission->id,
        'outcome' => 'not_created', 'submission_state_snapshot' => $chain->submissionSnapshot($order),
        'readiness_id' => CourierVerificationReadiness::sole()->id,
    ]);

    expect($chain->eligibility($order)['eligible'])->toBeTrue();
});

// ── Earned points ───────────────────────────────────────────────────────────

function grantEarnedPoints(Order $order, int $ratePerPoint = 10): void
{
    Setting::updateOrCreate(['key' => 'loyalty_earn_rate_bdt_per_point'], ['value' => $ratePerPoint, 'group' => 'loyalty']);
    Setting::updateOrCreate(['key' => 'loyalty_earn_delay_days'], ['value' => 7, 'group' => 'loyalty']);
    app(LoyaltyPointsService::class)->grantDueOrderEarnPoints();
}

test('a partial refund reverses earned points by the money actually refunded, not the returned units', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(['discount' => 100, 'shipping_cost' => 60], user: $user);
    grantEarnedPoints($order);
    expect($user->refresh()->points_balance)->toBe(90);

    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [1 => 1]); // worth 360, refunded 300 (damaged)
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 300, 'refund_method' => 'bKash'])->assertSessionHas('success');

    expect($user->refresh()->points_balance)->toBe(60) // floor(90 × 300 / 900) = 30 reversed
        ->and((int) ReferralPointTransaction::where('type', PointTransactionType::REVOKE_ORDER)->sum('points'))->toBe(-30);
});

test('a full refund reverses all earned points, idempotently, and the balance may go negative', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(user: $user);
    grantEarnedPoints($order); // 100 points
    $user->refresh()->decrement('points_balance', 80); // spent elsewhere -> 20

    courierVerifiedForRefund($order);
    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('success');
    app(LoyaltyPointsService::class)->reconcileEarnedPointsAfterRefund($order); // again: no change

    expect($user->refresh()->points_balance)->toBe(-80)
        ->and(ReferralPointTransaction::where('type', PointTransactionType::REVOKE_ORDER)->count())->toBe(1)
        ->and(app(LoyaltyPointsService::class)->maxRedeemablePoints($user, 1000))->toBe(0);

    grantEarnedPoints(refundOrder(user: $user), 20); // later earnings offset the debt: +50
    expect($user->refresh()->points_balance)->toBe(-30);
});

test('a grant made before the ledger is only reversed for refunds recorded after deployment', function () {
    $user = User::factory()->create(['points_balance' => 90]);
    $order = refundOrder(['discount' => 100], user: $user); // B = 1000 − 100 = 900
    ReferralPointTransaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'type' => PointTransactionType::EARN_ORDER, 'points' => 90]); // no basis recorded
    $order->forceFill(['loyalty_points_granted_at' => now()->subDays(2)])->saveQuietly();
    OrderRefund::factory()->backfilled()->create(['order_id' => $order->id, 'amount' => 200]);

    app(LoyaltyPointsService::class)->reconcileEarnedPointsAfterRefund($order);
    expect($user->refresh()->points_balance)->toBe(90); // the old refund alone reverses nothing

    OrderRefund::factory()->create(['order_id' => $order->id, 'amount' => 350]);
    app(LoyaltyPointsService::class)->reconcileEarnedPointsAfterRefund($order);
    expect($user->refresh()->points_balance)->toBe(55); // floor(90 × 350 / 900) = 35
});

// ── Redeemed points (PAY-RETURN-001-BATCH-2) ────────────────────────────────

test('a partial refund restores redeemed points proportionally to the refunded amount, not the full order', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(['discount' => 100, 'shipping_cost' => 60, 'points_redeemed' => 90], user: $user);

    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [1 => 1]); // worth 360, refunded 300 (damaged)
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 300, 'refund_method' => 'bKash'])->assertSessionHas('success');

    expect($user->refresh()->points_balance)->toBe(30) // floor(90 × 300 / 900) = 30 restored
        ->and((int) ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->sum('points'))->toBe(30);
});

test('successive partial returns cumulatively restore redeemed points without over- or under-crediting, and the final return picks up any rounding remainder', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    // 3 equal 100.00 lines, no discount — productNet = 30000 paisa, so each
    // 1/3 refund (10000 paisa) does not divide evenly into 100 points,
    // deliberately exercising the floor-rounding remainder.
    $order = refundOrder(['points_redeemed' => 100], [[100, 1], [100, 1], [100, 1]], user: $user);
    courierVerifiedForRefund($order);

    $first = approvedReturn($order, [0 => 1]);
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $first), ['refund_amount' => 100, 'refund_method' => 'bKash'])->assertSessionHas('success');
    expect($user->refresh()->points_balance)->toBe(33); // floor(100 × 10000 / 30000) = 33

    $second = approvedReturn($order, [1 => 1]);
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $second), ['refund_amount' => 100, 'refund_method' => 'bKash'])->assertSessionHas('success');
    expect($user->refresh()->points_balance)->toBe(66); // floor(100 × 20000 / 30000) = 66

    $third = approvedReturn($order, [2 => 1]);
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $third), ['refund_amount' => 100, 'refund_method' => 'bKash'])->assertSessionHas('success');

    // The third return fully closes the order (every unit now refunded), so
    // it goes through the pre-existing full-reversal path (`REFUND`) instead
    // of the proportional one — exercising the interaction fix in
    // `refundRedeemedPointsIfApplicable()`: it must restore only the 34
    // points still owed (100 − 66 already restored via REFUND_PARTIAL), not
    // the full 100 again, or the customer would be credited 166 points for
    // 100 redeemed.
    expect($user->refresh()->points_balance)->toBe(100)
        ->and((int) ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->sum('points'))->toBe(66)
        ->and((int) ReferralPointTransaction::where('type', PointTransactionType::REFUND)->sum('points'))->toBe(34);
});

test('a non-closing sequence of partial returns ends exactly on the original redeemed total with no remainder lost', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    // 4 equal lines so a 3-of-4 sequence of partial returns never fully
    // closes the order (one unit always remains unreturned), letting the
    // cumulative formula run to its productNet-capped target without the
    // full-reversal path taking over.
    $order = refundOrder(['points_redeemed' => 100], [[100, 1], [100, 1], [100, 1], [100, 1]], user: $user);
    courierVerifiedForRefund($order);

    foreach ([0, 1, 2] as $index) {
        $return = approvedReturn($order, [$index => 1]);
        actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $return), ['refund_amount' => 100, 'refund_method' => 'bKash'])->assertSessionHas('success');
    }

    // refunded = 30000 of productNet 40000 paisa: floor(100 × 30000 / 40000) = 75
    expect($user->refresh()->points_balance)->toBe(75)
        ->and((int) ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->sum('points'))->toBe(75);
});

test('a full-order refund still restores all redeemed points through the pre-existing, unchanged path', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(['points_redeemed' => 50], user: $user);

    courierVerifiedForRefund($order);
    actingAs(createAdmin(), 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHas('success');

    expect($user->refresh()->points_balance)->toBe(50)
        ->and(ReferralPointTransaction::where('type', PointTransactionType::REFUND)->count())->toBe(1)
        ->and(ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->exists())->toBeFalse();
});

test('reprocessing the same partial-return state never restores redeemed points twice', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(['discount' => 100, 'shipping_cost' => 60, 'points_redeemed' => 90], user: $user);

    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [1 => 1]);
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 300, 'refund_method' => 'bKash'])->assertSessionHas('success');

    app(LoyaltyPointsService::class)->reconcileRedeemedPointsAfterRefund($order); // again: no change
    app(LoyaltyPointsService::class)->reconcileRedeemedPointsAfterRefund($order); // and again

    expect($user->refresh()->points_balance)->toBe(30)
        ->and(ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->count())->toBe(1);
});

test('an order with no redeemed points is left untouched by a partial return', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $order = refundOrder(user: $user); // points_redeemed is null

    courierVerifiedForRefund($order);
    $returnRequest = approvedReturn($order, [1 => 1]);
    actingAs(createAdmin(), 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 300, 'refund_method' => 'bKash'])->assertSessionHas('success');

    expect($user->refresh()->points_balance)->toBe(0)
        ->and(ReferralPointTransaction::where('type', PointTransactionType::REFUND_PARTIAL)->exists())->toBeFalse();
});
