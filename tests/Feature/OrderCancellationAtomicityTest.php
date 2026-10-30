<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\CourierOrderStatusMapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/**
 * PROMO-LOY-002: an order's cancel/refund status change and every financial
 * side effect Order::booted() runs for it (redeemed-points refund, referral
 * reversal, stock restore) must commit or roll back together, from all
 * three entry points — Admin\OrderController::markRefunded(), ::cancel(),
 * and CourierOrderStatusMapper::sync(). These run on SQLite and prove the
 * transaction boundaries and the locked status re-check; real InnoDB
 * blocking is covered separately in LoyaltyPointsConcurrencyRaceTest.php.
 */
function atomicityOrder(User $user, array $overrides = []): Order
{
    $product = Product::factory()->create(['stock_quantity' => 10]);

    $order = Order::create(array_merge([
        'order_number' => 'ORD-ATOMIC-'.uniqid(),
        'user_id' => $user->id,
        'status' => OrderStatus::PROCESSING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 60,
        'discount' => 60,
        'tax' => 0,
        'total' => 1000,
        'points_redeemed' => 60,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
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

    return $order;
}

function failRedeemedPointsRefundLedger(bool &$enabled): void
{
    ReferralPointTransaction::creating(function (ReferralPointTransaction $transaction) use (&$enabled): void {
        if ($enabled && $transaction->type === PointTransactionType::REFUND) {
            throw new RuntimeException('Simulated REFUND ledger failure.');
        }
    });
}

function refundLedgerCount(Order $order): int
{
    return ReferralPointTransaction::where('order_id', $order->id)
        ->where('type', PointTransactionType::REFUND)
        ->count();
}

beforeEach(function () {
    $this->admin = createMotherAdmin();
    $this->customer = User::factory()->create(['points_balance' => 40]);
});

test('admin cancellation refunds redeemed points once and records one history row', function () {
    $order = atomicityOrder($this->customer);

    actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->points_refunded_at)->not->toBeNull();
    expect($this->customer->refresh()->points_balance)->toBe(100);
    expect(refundLedgerCount($order))->toBe(1);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
});

test('marking an order refunded twice credits the redeemed points only once', function () {
    $order = atomicityOrder($this->customer);

    actingAs($this->admin, 'admin')->patch(route('admin.orders.markRefunded', $order))->assertSessionHas('success');
    actingAs($this->admin, 'admin')->patch(route('admin.orders.markRefunded', $order))->assertSessionHas('error');

    expect($this->customer->refresh()->points_balance)->toBe(100);
    expect(refundLedgerCount($order))->toBe(1);
    expect(OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->count())->toBe(1);
});

test('cancelling and then refunding the same order credits the redeemed points only once', function () {
    $order = atomicityOrder($this->customer);

    actingAs($this->admin, 'admin')->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');
    actingAs($this->admin, 'admin')->patch(route('admin.orders.markRefunded', $order))->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
    expect($this->customer->refresh()->points_balance)->toBe(100);
    expect(refundLedgerCount($order))->toBe(1);
});

test('cancelling an order whose redeemed points were already refunded credits nothing', function () {
    $order = atomicityOrder($this->customer);
    $order->forceFill(['points_refunded_at' => now()->subDay()])->saveQuietly();

    actingAs($this->admin, 'admin')->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($this->customer->refresh()->points_balance)->toBe(40);
    expect(refundLedgerCount($order))->toBe(0);
});

test('a second cancel submitted with a stale order is rejected without a second history row or email', function () {
    Mail::fake();
    $order = atomicityOrder($this->customer);

    actingAs($this->admin, 'admin')->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');
    actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('error', 'Orders that are already cancelled cannot be cancelled.');

    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    expect(refundLedgerCount($order))->toBe(1);
    Mail::assertQueued(OrderCancelledMail::class, 1);
});

test('a failing REFUND ledger entry rolls back the whole mark-refunded action', function () {
    $order = atomicityOrder($this->customer);
    $productId = $order->items()->value('product_id');
    $failing = true;
    failRedeemedPointsRefundLedger($failing);

    actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.markRefunded', $order))
        ->assertServerError();

    expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and($order->points_refunded_at)->toBeNull();
    expect($this->customer->refresh()->points_balance)->toBe(40);
    expect(refundLedgerCount($order))->toBe(0);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    expect(Product::find($productId)->stock_quantity)->toBe(10);
});

test('a failing referral reversal rolls back the whole cancel action', function () {
    $order = atomicityOrder($this->customer, ['used_referral_discount' => true]);
    $this->customer->forceFill(['referral_discount_used_order_id' => $order->id])->save();

    User::saving(function (User $user): void {
        if ($user->isDirty('referral_discount_used_order_id') && $user->referral_discount_used_order_id === null) {
            throw new RuntimeException('Simulated referral reversal failure.');
        }
    });

    actingAs($this->admin, 'admin')
        ->patch(route('admin.orders.cancel', $order))
        ->assertServerError();

    expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and($order->cancellation_reason)->toBeNull()
        ->and($order->points_refunded_at)->toBeNull();

    $customer = $this->customer->refresh();
    expect($customer->referral_discount_used_order_id)->toBe($order->id)
        ->and($customer->points_balance)->toBe(40);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
});

test('a courier cancellation whose refund fails is rolled back entirely and succeeds on the next report', function () {
    $order = atomicityOrder($this->customer);
    $failing = true;
    failRedeemedPointsRefundLedger($failing);

    expect(fn () => CourierOrderStatusMapper::sync($order, 'steadfast', 'cancelled_parcells'))
        ->toThrow(RuntimeException::class, 'Simulated REFUND ledger failure.');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PROCESSING)
        ->and($order->steadfast_status)->toBeNull()
        ->and($order->points_refunded_at)->toBeNull();
    expect($this->customer->refresh()->points_balance)->toBe(40);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);

    // The raw status was rolled back too, so the courier's retry of the same
    // report is not swallowed as "unchanged" — it now goes through.
    $failing = false;
    CourierOrderStatusMapper::sync($order, 'steadfast', 'cancelled_parcells');

    expect($order->status)->toBe(OrderStatus::CANCELLED);
    expect($this->customer->refresh()->points_balance)->toBe(100);
    expect(refundLedgerCount($order))->toBe(1);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
});

test('the pathao sync command keeps syncing other orders when one order rolls back', function () {
    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
        ['key' => 'pathao_store_id', 'value' => json_encode('555'), 'group' => 'courier'],
        ['key' => 'pathao_token', 'value' => json_encode([
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String(),
        ]), 'group' => 'courier'],
    ]);

    $failingOrder = atomicityOrder($this->customer, ['order_number' => 'ORD-ROLLED-BACK', 'pathao_consignment_id' => 'CONSIGN-FAIL']);
    $otherOrder = atomicityOrder(User::factory()->create(), ['order_number' => 'ORD-OTHER', 'points_redeemed' => 0, 'pathao_consignment_id' => 'CONSIGN-OK']);

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-FAIL/info' => Http::response(['type' => 'success', 'data' => ['order_status_slug' => 'returned']]),
        '*/aladdin/api/v1/orders/CONSIGN-OK/info' => Http::response(['type' => 'success', 'data' => ['order_status_slug' => 'in_transit']]),
    ]);

    $failing = true;
    failRedeemedPointsRefundLedger($failing);

    $this->artisan('pathao:sync-statuses')
        ->expectsOutputToContain('Order ORD-ROLLED-BACK: sync failed and was rolled back')
        ->assertSuccessful();

    expect($failingOrder->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and($failingOrder->pathao_order_status)->toBeNull();
    expect($this->customer->refresh()->points_balance)->toBe(40);
    expect($otherOrder->refresh()->status)->toBe(OrderStatus::SHIPPED);
});

/** @param  array<string, mixed>  $payload */
function postAtomicitySteadfastWebhook(array $payload): TestResponse
{
    $body = json_encode($payload);

    return test()->call(
        'POST',
        route('steadfast.webhook'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => hash_hmac('sha256', $body, 'atomicity-secret')],
        $body,
    );
}

test('a steadfast cancellation webhook whose refund fails returns 500, persists nothing, and completes once on redelivery', function () {
    Setting::updateOrCreate(['key' => 'steadfast_webhook_secret'], ['value' => 'atomicity-secret', 'group' => 'courier']);
    $order = atomicityOrder($this->customer, ['steadfast_consignment_id' => 'SF-ATOMIC-1']);
    $payload = [
        'notification_type' => 'delivery_status',
        'invoice' => $order->order_number,
        'status' => 'cancelled_parcells',
        'tracking_message' => 'Parcel cancelled.',
    ];
    $failing = true;
    failRedeemedPointsRefundLedger($failing);

    postAtomicitySteadfastWebhook($payload)->assertStatus(500);

    expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
        ->and($order->steadfast_status)->toBeNull();
    expect($this->customer->refresh()->points_balance)->toBe(40);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);

    $failing = false;
    postAtomicitySteadfastWebhook($payload)->assertOk();
    postAtomicitySteadfastWebhook($payload)->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->steadfast_status)->toBe('cancelled_parcells');
    expect($this->customer->refresh()->points_balance)->toBe(100);
    expect(refundLedgerCount($order))->toBe(1);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
});
