<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Mail\LowStockAlertMail;
use App\Mail\NewOrderAlertMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReviewApprovedMail;
use App\Mail\ShippedWithTrackingMail;
use App\Mail\WelcomeMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\CourierOrderStatusMapper;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function codCheckoutPayload(Product $product, int $quantity = 1): array
{
    return [
        'name' => 'Notify Test', 'email' => 'buyer@example.com', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
    ];
}

test('placing a COD order emails the customer and the mother admin', function () {
    Mail::fake();
    $admin = createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create(['email' => 'buyer@example.com']);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), codCheckoutPayload($product))->assertRedirect();

    $order = Order::query()->firstOrFail();

    Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->order->is($order));
    Mail::assertQueued(NewOrderAlertMail::class, fn ($mail) => $mail->order->is($order));
});

test('a checkout that rolls back due to insufficient stock never creates the order', function () {
    // Mail::to()->send() on a ShouldQueue mailable with ->afterCommit() is
    // dispatched via Order::booted()'s `created` event, which fires
    // synchronously as part of Order::create() — before the surrounding
    // DB::transaction() can roll back on a later line (e.g. a sibling
    // cart item failing its stock check). ->afterCommit() is what
    // actually protects against sending for a row that never commits:
    // Laravel defers the real queue push via DatabaseTransactionsManager
    // and discards it entirely on rollback (verified by reading
    // SendQueuedMailable::__construct() and Queue::shouldDispatchAfterCommit()
    // in the framework source — both correctly propagate/honor it).
    // Mail::fake() replaces the Mailer *before* that transaction-aware
    // queue layer, so it cannot itself observe this distinction — what
    // IS fully testable here, and what actually matters for data
    // integrity, is that the rolled-back order never exists.
    createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create(['email' => 'buyer@example.com']);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), codCheckoutPayload($product, 5))
        ->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

/**
 * Owner email policy (2026-11-14): a customer gets exactly three order
 * emails — placed, delivered, cancelled — and nothing for any other status.
 */
function notifyOrder(string $number, string $status, string $paymentStatus = 'unpaid'): Order
{
    $user = User::factory()->create(['email' => 'buyer@example.com']);

    return Order::create([
        'order_number' => $number, 'user_id' => $user->id, 'status' => $status,
        'payment_status' => $paymentStatus, 'payment_method' => 'cod', 'subtotal' => 100,
        'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['email' => 'buyer@example.com'], 'billing_address' => [],
    ]);
}

test('placing an order sends the customer the order confirmation email', function () {
    Mail::fake();

    $order = notifyOrder('ORD-NOTIFY-0', 'pending');

    Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->order->is($order) && $mail->hasTo('buyer@example.com'));
});

test('delivering an order sends the delivery email', function () {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-1', 'shipped');
    Mail::fake(); // clear the creation-time emails before asserting the transition

    $order->update(['status' => OrderStatus::DELIVERED]);

    Mail::assertQueued(OrderDeliveredMail::class, fn ($mail) => $mail->order->is($order));
});

test('cancelling an order sends OrderCancelledMail', function () {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-2', 'pending');
    Mail::fake();

    $order->update(['status' => OrderStatus::CANCELLED]);

    Mail::assertQueued(OrderCancelledMail::class, fn ($mail) => $mail->order->is($order));
    Mail::assertNotQueued(OrderStatusUpdatedMail::class);
});

test('no other status change sends the customer any email', function (string $from, OrderStatus $to) {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-3-'.$to->value, $from, 'paid');
    Mail::fake();

    $order->update(['status' => $to]);

    Mail::assertNotQueued(ShippedWithTrackingMail::class);
    Mail::assertNotQueued(OrderStatusUpdatedMail::class);
    Mail::assertNotQueued(OrderDeliveredMail::class);
    Mail::assertNotQueued(OrderCancelledMail::class);
    Mail::assertNotQueued(OrderPlacedMail::class);
})->with([
    'pending to processing' => ['pending', OrderStatus::PROCESSING],
    'processing to shipped' => ['processing', OrderStatus::SHIPPED],
    'shipped back to processing' => ['shipped', OrderStatus::PROCESSING],
    'processing back to pending' => ['processing', OrderStatus::PENDING],
    'delivered to refunded' => ['delivered', OrderStatus::REFUNDED],
]);

test('re-asserting the same status again does not resend the email', function () {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-4', 'shipped');
    Mail::fake();

    $order->update(['status' => OrderStatus::DELIVERED]);
    Mail::assertQueued(OrderDeliveredMail::class, 1);

    // Simulates a retried courier report of the same mapped status.
    $order->update(['status' => OrderStatus::DELIVERED]);
    Mail::assertQueued(OrderDeliveredMail::class, 1);
});

test('courier reports send customer email only for delivered and cancelled, never twice', function () {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-5', 'pending');
    $order->forceFill(['pathao_consignment_id' => 'PT-NOTIFY-5'])->save();
    Mail::fake();

    foreach (['pending', 'picked', 'in_transit', 'on_hold', 'exchange', 'reprocess_pending', 'reprocessed', 'at_inventory',
        'return_requested', 'returned', 'Partial_Delivery', 'lost', 'damaged', 'assigned_for_delivery'] as $raw) {
        CourierOrderStatusMapper::sync($order->refresh(), 'pathao', $raw);
    }
    Mail::assertNothingQueued();

    CourierOrderStatusMapper::sync($order->refresh(), 'pathao', 'delivery');
    CourierOrderStatusMapper::sync($order->refresh(), 'pathao', 'delivery');
    CourierOrderStatusMapper::sync($order->refresh(), 'pathao', 'Delivered');

    Mail::assertQueued(OrderDeliveredMail::class, 1);
    Mail::assertNotQueued(ShippedWithTrackingMail::class);
    Mail::assertNotQueued(OrderStatusUpdatedMail::class);
});

test('a courier cancellation sends the cancellation email once', function () {
    Mail::fake();
    $order = notifyOrder('ORD-NOTIFY-7', 'shipped');
    $order->forceFill(['steadfast_consignment_id' => 'SF-NOTIFY-7'])->save();
    Mail::fake();

    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'cancelled');
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', 'cancelled');

    Mail::assertQueued(OrderCancelledMail::class, 1);
});

test('a guest order with no email on file sends no customer email but still alerts the admin', function () {
    Mail::fake();
    createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);

    $order = Order::create([
        'order_number' => 'ORD-NOTIFY-5', 'user_id' => null, 'status' => 'pending',
        'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 100,
        'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['email' => ''], 'billing_address' => [],
    ]);

    Mail::assertNotQueued(OrderPlacedMail::class);
    Mail::assertQueued(NewOrderAlertMail::class, fn ($mail) => $mail->order->is($order));
});

test('status-change emails always use the order owner email, never the acting admins own address', function () {
    Mail::fake();
    $admin = createAdmin(['name' => 'Acting Admin', 'email' => 'admin-acting@example.com', 'password' => bcrypt('x')]);
    $customer = User::factory()->create(['email' => 'real-customer@example.com']);
    $order = Order::create([
        'order_number' => 'ORD-NOTIFY-6', 'user_id' => $customer->id, 'status' => 'pending',
        'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 100,
        'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['email' => 'real-customer@example.com'], 'billing_address' => [],
    ]);
    Mail::fake();

    actingAs($admin, 'admin');
    $order->update(['status' => OrderStatus::CANCELLED]);
    OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'cancelled']);

    Mail::assertQueued(OrderCancelledMail::class, function ($mail) {
        return $mail->hasTo('real-customer@example.com') && ! $mail->hasTo('admin-acting@example.com');
    });
});

test('verifying an email address sends WelcomeMail exactly once', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create([
        'email' => 'newcustomer@example.com',
        'email_verification_code' => User::hashEmailVerificationCode('123456', 'newcustomer@example.com'),
        'email_verification_code_expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($user)->post(route('verification.verify-code'), ['code' => '123456'])
        ->assertRedirect();

    Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->user->is($user));

    // Visiting again now that they're verified must not resend.
    $this->actingAs($user)->post(route('verification.verify-code'), ['code' => '123456']);
    Mail::assertQueued(WelcomeMail::class, 1);
});

test('approving a review sends ReviewApprovedMail once, not on a repeat approve', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'reviewer@example.com']);
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('x')]);
    $product = Product::factory()->create();
    $review = Review::create([
        'product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5,
        'title' => 'Great', 'body' => 'Loved it', 'is_approved' => false,
    ]);

    actingAs($admin, 'admin');
    $this->patch(route('admin.reviews.approve', $review))->assertRedirect();
    Mail::assertQueued(ReviewApprovedMail::class, fn ($mail) => $mail->review->is($review));

    $this->patch(route('admin.reviews.approve', $review));
    Mail::assertQueued(ReviewApprovedMail::class, 1);
});

test('stock crossing the low-stock threshold alerts the mother admin exactly once', function () {
    Mail::fake();
    createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 6, 'low_stock_threshold' => 5]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), codCheckoutPayload($product, 1))->assertRedirect();

    // 6 -> 5: still at (not below) the first unit crossing the line.
    Mail::assertQueued(LowStockAlertMail::class, 1);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), codCheckoutPayload($product, 1))->assertRedirect();

    // 5 -> 4: already below threshold, must not re-alert.
    Mail::assertQueued(LowStockAlertMail::class, 1);
});

test('stock staying above the low-stock threshold does not alert', function () {
    Mail::fake();
    createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50, 'low_stock_threshold' => 5]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), codCheckoutPayload($product, 1))->assertRedirect();

    Mail::assertNotQueued(LowStockAlertMail::class);
});
