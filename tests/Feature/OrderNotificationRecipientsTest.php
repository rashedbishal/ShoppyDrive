<?php

declare(strict_types=1);

/*
 * The shop-side order emails (new-order alert and order-cancelled alert) go to
 * the addresses configured in Admin > Settings; an empty field means the main
 * admin. Customers' own confirmation/cancellation emails are unaffected.
 */

use App\Enums\OrderStatus;
use App\Mail\CancelledOrderAlertMail;
use App\Mail\NewOrderAlertMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderPlacedMail;
use App\Mail\ShippedWithTrackingMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\LayoutCache;
use App\Support\OrderNotificationRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function notificationOrder(string $status = 'pending'): Order
{
    $user = User::factory()->create(['email' => 'buyer@example.com']);

    return Order::create([
        'order_number' => 'ORD-RECIP-'.uniqid(), 'user_id' => $user->id, 'status' => $status,
        'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 100,
        'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'Buyer', 'phone' => '01700000000', 'email' => 'buyer@example.com'], 'billing_address' => [],
    ]);
}

function setNotificationEmail(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'notifications']);
}

beforeEach(function () {
    createMotherAdmin(['email' => 'owner@example.com']);
});

// ── New-order alert ──────────────────────────────────────────────────────────

test('a new order alerts the configured order confirmation email instead of the main admin', function () {
    setNotificationEmail(OrderNotificationRecipients::NEW_ORDER_KEY, 'orders@shop.test');
    Mail::fake();

    $order = notificationOrder();

    Mail::assertQueued(NewOrderAlertMail::class, fn ($mail) => $mail->hasTo('orders@shop.test') && $mail->order->is($order));
    Mail::assertQueued(NewOrderAlertMail::class, 1);
    // The customer's own confirmation is untouched.
    Mail::assertQueued(OrderPlacedMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
});

test('with no order confirmation email set, the main admin still gets the new-order alert', function () {
    Mail::fake();

    notificationOrder();

    Mail::assertQueued(NewOrderAlertMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});

test('a blank or invalid stored address falls back to the main admin', function (mixed $stored) {
    setNotificationEmail(OrderNotificationRecipients::NEW_ORDER_KEY, $stored);
    Mail::fake();

    notificationOrder();

    Mail::assertQueued(NewOrderAlertMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
})->with(['null' => [null], 'empty' => [''], 'spaces' => ['   '], 'not an email' => ['not-an-email']]);

// ── Cancellation alert ───────────────────────────────────────────────────────

test('cancelling an order alerts the configured cancellation email and still emails the customer', function () {
    setNotificationEmail(OrderNotificationRecipients::CANCELLATION_KEY, 'cancel@shop.test');
    $order = notificationOrder();
    Mail::fake();

    $order->update(['status' => OrderStatus::CANCELLED]);

    Mail::assertQueued(CancelledOrderAlertMail::class, fn ($mail) => $mail->hasTo('cancel@shop.test') && $mail->order->is($order));
    Mail::assertQueued(CancelledOrderAlertMail::class, 1);
    Mail::assertQueued(OrderCancelledMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
});

test('with no cancellation email set, the main admin is told about the cancellation', function () {
    $order = notificationOrder();
    Mail::fake();

    $order->update(['status' => OrderStatus::CANCELLED]);

    Mail::assertQueued(CancelledOrderAlertMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});

test('the two addresses are independent of each other', function () {
    setNotificationEmail(OrderNotificationRecipients::NEW_ORDER_KEY, 'orders@shop.test');
    $order = notificationOrder();
    Mail::fake();

    $order->update(['status' => OrderStatus::CANCELLED]);

    // Only the new-order address was configured; the cancellation alert still uses the main admin.
    Mail::assertQueued(CancelledOrderAlertMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});

test('only a cancellation sends the cancellation alert, and only once', function () {
    $order = notificationOrder('processing');
    Mail::fake();

    $order->update(['status' => OrderStatus::SHIPPED]);
    Mail::assertNotQueued(CancelledOrderAlertMail::class);
    Mail::assertQueued(ShippedWithTrackingMail::class);

    $order->update(['status' => OrderStatus::CANCELLED]);
    $order->update(['status' => OrderStatus::CANCELLED]);

    Mail::assertQueued(CancelledOrderAlertMail::class, 1);
});

test('an admin cancelling from the order page triggers the alert and the customer email', function () {
    setNotificationEmail(OrderNotificationRecipients::CANCELLATION_KEY, 'cancel@shop.test');
    $order = notificationOrder();
    Mail::fake();

    actingAs(createAdmin(['email' => 'staff@example.com']), 'admin')
        ->patch(route('admin.orders.cancel', $order), ['reason' => 'Out of stock'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
    Mail::assertQueued(CancelledOrderAlertMail::class, fn ($mail) => $mail->hasTo('cancel@shop.test'));
    Mail::assertQueued(OrderCancelledMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
});

test('the cancellation alert renders the order, reason and customer', function () {
    $order = notificationOrder();
    $order->forceFill(['cancellation_reason' => 'Customer asked to cancel'])->save();

    $html = (new CancelledOrderAlertMail($order->fresh()))->render();

    expect($html)->toContain($order->order_number)->toContain('Customer asked to cancel')->toContain('Buyer');
});

// ── Settings page ────────────────────────────────────────────────────────────

test('the main settings page saves both addresses and shows them back', function () {
    actingAs(createAdmin(['email' => 'staff2@example.com']), 'admin')
        ->put(route('admin.settings.update'), [
            'order_notification_email' => 'orders@shop.test',
            'order_cancellation_email' => 'cancel@shop.test',
        ])->assertSessionHasNoErrors();

    expect(OrderNotificationRecipients::newOrder())->toBe('orders@shop.test')
        ->and(OrderNotificationRecipients::cancellation())->toBe('cancel@shop.test');

    actingAs(createAdmin(['email' => 'staff3@example.com']), 'admin')
        ->get(route('admin.settings.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.order_notification_email', 'orders@shop.test')
            ->where('settings.order_cancellation_email', 'cancel@shop.test'));
});

test('clearing a field switches that alert back to the main admin', function () {
    setNotificationEmail(OrderNotificationRecipients::NEW_ORDER_KEY, 'orders@shop.test');

    actingAs(createAdmin(['email' => 'staff4@example.com']), 'admin')
        ->put(route('admin.settings.update'), ['order_notification_email' => ''])
        ->assertSessionHasNoErrors();

    expect(OrderNotificationRecipients::newOrder())->toBe('owner@example.com');
});

test('an invalid address is rejected and nothing is saved', function () {
    actingAs(createAdmin(['email' => 'staff5@example.com']), 'admin')
        ->put(route('admin.settings.update'), ['order_cancellation_email' => 'not-an-email'])
        ->assertSessionHasErrors('order_cancellation_email');

    expect(Setting::where('key', OrderNotificationRecipients::CANCELLATION_KEY)->exists())->toBeFalse();
});

test('an admin without the settings permission cannot change the addresses', function () {
    $staff = Admin::create(['name' => 'Limited', 'email' => 'limited@example.com', 'password' => 'password', 'permissions' => ['orders' => ['view']]]);

    actingAs($staff, 'admin')
        ->put(route('admin.settings.update'), ['order_notification_email' => 'orders@shop.test'])
        ->assertForbidden();

    expect(Setting::where('key', OrderNotificationRecipients::NEW_ORDER_KEY)->exists())->toBeFalse();
});

test('the addresses are never shared with the storefront', function () {
    setNotificationEmail(OrderNotificationRecipients::NEW_ORDER_KEY, 'orders@shop.test');
    setNotificationEmail(OrderNotificationRecipients::CANCELLATION_KEY, 'cancel@shop.test');
    LayoutCache::forget(LayoutCache::SETTINGS);

    expect(json_encode(LayoutCache::settings()))->not->toContain('shop.test');
});
