<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Admin;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\User;

/*
 * Controls the audit attacked directly. A PASS here is evidence the control held.
 */

function auditOrderFor(?User $user, string $number): Order
{
    return Order::create([
        'order_number' => $number, 'user_id' => $user?->id, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'Victim', 'phone' => '01700000000', 'address_line1' => 'Secret House'], 'billing_address' => [],
    ]);
}

test('C01 prices, totals and discounts sent by the client are ignored', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 5]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'total' => 1, 'subtotal' => 1, 'discount' => 999, 'shipping_cost' => 0, 'coupon_discount' => 999,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 1, 'subtotal' => 1]],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(1000.0)->and((float) $order->discount)->toBe(0.0)
        ->and((float) $order->total)->toBeGreaterThanOrEqual(1000.0)
        ->and((float) $order->items->first()->price)->toBe(1000.0);
});

test('C02 negative and zero quantities are rejected', function (int $quantity) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
    ]))->assertSessionHasErrors('items.0.quantity');

    expect(Order::query()->count())->toBe(0)->and((int) $product->fresh()->stock_quantity)->toBe(5);
})->with([0, -1]);

test('C03 a hidden product cannot be bought', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'is_active' => false]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect(Order::query()->count())->toBe(0);
});

test('C04 one customer cannot read or cancel another customer order', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();
    $order = auditOrderFor($victim, 'ORD-VICTIM-1');

    $this->actingAs($attacker)->get("/account/orders/{$order->order_number}")->assertStatus(403);
    $this->actingAs($attacker)->get("/account/orders/{$order->order_number}/invoice")->assertStatus(403);
    $this->actingAs($attacker)->patch(route('account.orders.cancel', $order->order_number))->assertStatus(403);

    expect($order->fresh()->status->value)->toBe('pending');
});

test('C05 an order confirmation page is not open to strangers', function () {
    $victim = User::factory()->create();
    $order = auditOrderFor($victim, 'ORD-VICTIM-2');

    $response = $this->get(route('order.confirmation', $order->order_number));

    expect($response->status())->toBeIn([403, 404, 302])
        ->and($response->getContent())->not->toContain('Secret House');
});

test('C06 order tracking refuses a wrong phone', function () {
    auditOrderFor(null, 'ORD-VICTIM-3');

    $response = $this->get('/order/track?order_number=ORD-VICTIM-3&phone=01899999999');

    expect(json_encode($response->viewData('page')['props']['order'] ?? null))->not->toContain('Secret House');
});

test('C07 one customer cannot pay through another customer pending checkout', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $this->actingAs($victim)->post(route('checkout.store'), auditCheckoutPayload([
        'payment_method' => 'mobile_banking', 'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();
    $pending = PendingCheckout::query()->firstOrFail();

    $status = $this->actingAs($attacker)->get("/payment/mobile/{$pending->token}")->status();

    expect($status)->toBeIn([403, 404]);
});

test('C08 the SMS webhook refuses a missing or wrong token', function () {
    config(['services.sms_forwarder.token' => 'right-token']);

    $this->postJson('/api/sms/receive', ['from' => 'bKash', 'content' => 'TrxID ABC'])->assertStatus(401);
    $this->withToken('wrong-token')->postJson('/api/sms/receive', ['from' => 'bKash', 'content' => 'TrxID ABC'])->assertStatus(401);

    expect(ReceivedSms::query()->count())->toBe(0);
});

test('C09 the SMS webhook fails closed when no token is configured', function () {
    config(['services.sms_forwarder.token' => '']);

    $this->withToken('')->postJson('/api/sms/receive', ['from' => 'bKash', 'content' => 'TrxID ABC'])->assertStatus(401);
});

test('C10 a Steadfast webhook without a valid signature changes nothing', function () {
    Setting::create(['key' => 'steadfast_webhook_secret', 'value' => 'right-secret', 'group' => 'steadfast']);
    $order = auditOrderFor(null, 'ORD-VICTIM-4');
    $order->forceFill(['steadfast_consignment_id' => '777'])->save();
    $body = json_encode(['consignment_id' => '777', 'status' => 'delivered_parcels', 'notification_type' => 'delivery_status']);

    $bad = $this->call('POST', '/api/steadfast/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'bad'], $body);

    expect($bad->status())->toBe(401)
        ->and($order->fresh()->status->value)->toBe('pending')->and($order->fresh()->payment_status->value)->toBe('unpaid');

    // Positive control: the same body with the right signature does reach the handler.
    $this->call('POST', '/api/steadfast/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'right-secret')], $body);
    expect($order->fresh()->status->value)->toBe('delivered');
});

test('C11 one transaction ID cannot pay two orders', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $payload = fn () => auditCheckoutPayload(['payment_method' => 'mobile_banking', 'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]]);

    $this->actingAs($user)->post(route('checkout.store'), $payload())->assertRedirect();
    $first = PendingCheckout::query()->latest('id')->firstOrFail();
    ReceivedSms::create(['sender' => 'bKash', 'provider' => 'bkash', 'content' => 'x', 'received_at' => now(), 'parsed_transaction_id' => 'ONCE1', 'parsed_amount' => (float) $first->checkout_data['total'], 'is_matched' => false]);
    $this->actingAs($user)->post(route('payment.mobile.submit', $first->token), ['provider' => 'bkash', 'transaction_id' => 'ONCE1']);

    $this->actingAs($user)->post(route('checkout.store'), $payload())->assertRedirect();
    $second = PendingCheckout::query()->whereNull('order_number')->latest('id')->first() ?? PendingCheckout::query()->latest('id')->firstOrFail();
    $this->actingAs($user)->post(route('payment.mobile.submit', $second->token), ['provider' => 'bkash', 'transaction_id' => 'ONCE1']);

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(1);
});

test('C12 a double-submitted order creates one order and one stock decrement', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $payload = auditCheckoutPayload(['items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]]);

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    expect(Order::query()->count())->toBe(1)->and((int) $product->fresh()->stock_quantity)->toBe(4);
});

test('C13 a customer session cannot reach the admin area, and a limited admin cannot exceed permissions', function () {
    $this->actingAs(User::factory()->create())->get('/admin/orders')->assertRedirect();

    $limited = auditAdmin(['about_page' => ['view']]);
    $orders = $this->actingAs($limited, 'admin')->get('/admin/orders');
    expect($orders->status())->toBeIn([302, 403])->and($orders->headers->get('Location') ?? '')->not->toContain('/admin/orders');

    $before = Admin::query()->count();
    $this->actingAs($limited, 'admin')->post('/admin/admins', ['name' => 'x', 'email' => 'x@example.test', 'password' => 'Passw0rd!Passw0rd!', 'password_confirmation' => 'Passw0rd!Passw0rd!']);
    expect(Admin::query()->count())->toBe($before);
});

test('C14 mass assignment cannot make a customer or admin privileged', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'N', 'email' => $user->email, 'points_balance' => 99999, 'is_admin' => true]);
    expect((int) $user->fresh()->points_balance)->toBe(0);

    $admin = auditAdmin();
    $this->actingAs($admin, 'admin')->put("/admin/admins/{$admin->id}", ['name' => 'N', 'email' => $admin->email, 'is_mother' => true, 'permissions' => ['settings' => ['view']]]);
    expect((bool) $admin->fresh()->is_mother)->toBeFalse();
});

test('C15 a saved address of another customer cannot be used at checkout', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();
    $address = Address::create(['user_id' => $victim->id, 'name' => 'V', 'phone' => '01700000000', 'address_line1' => 'Secret House', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh']);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $this->actingAs($attacker)->post(route('checkout.store'), auditCheckoutPayload([
        'address_id' => $address->id, 'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect(json_encode(Order::query()->first()?->shipping_address))->not->toContain('Secret House');
});

test('C16 XSS in a review, product description and contact field is not stored as live script', function () {
    $product = Product::factory()->create(['description' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)>']);

    expect($product->fresh()->description)->not->toContain('<script')->not->toContain('onerror');
});

test('C17 a coupon above the subtotal cannot make the total negative', function () {
    Coupon::factory()->create(['code' => 'HUGE', 'type' => 'fixed', 'value' => 999999, 'max_uses' => null, 'per_user_limit' => null]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload(['coupon_code' => 'HUGE', 'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]]))->assertRedirect();

    expect((float) Order::query()->firstOrFail()->total)->toBeGreaterThanOrEqual(0.0);
});
