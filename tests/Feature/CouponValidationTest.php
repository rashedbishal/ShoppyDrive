<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function couponCheckoutPayload(Product $product, int $quantity, string $couponCode, array $overrides = []): array
{
    return array_merge([
        'name' => 'Coupon Test', 'email' => 'couponbuyer@example.com', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'coupon_code' => $couponCode,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
    ], $overrides);
}

test('a coupon below its minimum order amount is rejected', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['min_order_amount' => 500]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $response = $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code));

    $response->assertSessionHasErrors('coupon_code');
    expect(Order::query()->count())->toBe(0);
    expect($coupon->refresh()->used_count)->toBe(0);
});

test('BIZ-013: a coupon rejected at submission flashes a visible error, not just the invisible error bag', function () {
    // Checkout.tsx only renders field-level errors for the named
    // shipping-address inputs — nothing reads a coupon_code key from the
    // Inertia errors bag, so a rejection needs the same `error` session
    // flash the page's banner already reads for other refusals.
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['min_order_amount' => 500]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $response = $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code));

    $response->assertSessionHasErrors('coupon_code');
    expect($response->getSession()->get('error'))->not->toBeNull();
});

test('a coupon outside its start/end window is rejected', function () {
    $user = User::factory()->create();
    $futureCoupon = Coupon::factory()->create(['starts_at' => now()->addDay()]);
    $expiredCoupon = Coupon::factory()->create(['expires_at' => now()->subDay()]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $futureCoupon->code))
        ->assertSessionHasErrors('coupon_code');

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $expiredCoupon->code))
        ->assertSessionHasErrors('coupon_code');

    expect(Order::query()->count())->toBe(0);
});

test('an inactive coupon is rejected', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['is_active' => false]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertSessionHasErrors('coupon_code');
});

test('an unknown coupon code is rejected with a clear error, not silently ignored', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $response = $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, 'DOES-NOT-EXIST'));

    $response->assertSessionHasErrors('coupon_code');
    expect(Order::query()->count())->toBe(0);
});

test('a successful COD checkout consumes exactly one global use and records a CouponUse row', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 20]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($coupon->refresh()->used_count)->toBe(1);
    expect((float) $order->discount)->toBe(20.0);

    $use = CouponUse::where('order_id', $order->id)->first();
    expect($use)->not->toBeNull();
    expect($use->user_id)->toBe($user->id);
    expect((float) $use->discount_amount)->toBe(20.0);
});

test('the global usage limit is enforced and blocks checkout once reached', function () {
    $coupon = Coupon::factory()->create(['max_uses' => 1, 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $firstUser = User::factory()->create();
    Cart::query()->where('user_id', $firstUser->id)->delete();
    $this->actingAs($firstUser)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);

    $secondUser = User::factory()->create();
    Cart::query()->where('user_id', $secondUser->id)->delete();
    $response = $this->actingAs($secondUser)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code));

    $response->assertSessionHasErrors('coupon_code');
    expect(Order::query()->count())->toBe(1);
    expect($coupon->refresh()->used_count)->toBe(1);
});

test('the per-user usage limit is enforced independently of the global limit', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);

    // Same user tries to use it again — blocked by their own limit even
    // though the coupon has no global max_uses at all.
    Cart::query()->where('user_id', $user->id)->delete();
    $response = $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code));

    $response->assertSessionHasErrors('coupon_code');
    expect($coupon->refresh()->used_count)->toBe(1);

    // A different user can still use it. (BIZ-015: a different customer
    // means a different phone too; the same phone counts as the same person.)
    $otherUser = User::factory()->create();
    Cart::query()->where('user_id', $otherUser->id)->delete();
    $this->actingAs($otherUser)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01800000000']))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(2);
});

test('PROMO-001: a guest cannot reuse a per_user_limit coupon with the same checkout phone number', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);

    // Same guest, same phone, tries again — must be blocked even though
    // there is no account for per_user_limit to key off.
    $response = $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']));

    $response->assertSessionHasErrors('coupon_code');
    expect($coupon->refresh()->used_count)->toBe(1);
    expect(Order::where('coupon_id', $coupon->id)->count())->toBe(1);
});

test('PROMO-001: a different guest phone number can still use the same per_user_limit coupon', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']))
        ->assertRedirect();

    $response = $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01722222222']));

    $response->assertRedirect();
    expect($coupon->refresh()->used_count)->toBe(2);
});

test('PROMO-001: a guest whose prior order was cancelled (coupon released) can redeem the coupon again', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $admin = createAdmin(['name' => 'Admin', 'email' => 'promo001-admin@example.com', 'password' => bcrypt('password')]);

    $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']))
        ->assertRedirect();

    $order = Order::where('coupon_id', $coupon->id)->firstOrFail();
    $this->actingAs($admin, 'admin')->patch(route('admin.orders.cancel', $order), ['reason' => 'test'])->assertRedirect();
    expect($order->refresh()->coupon_released_at)->not->toBeNull();

    // The admin session from the action above would otherwise persist and
    // have RedirectAdminToDashboard intercept the next "guest" request
    // before it ever reaches checkout.
    auth('admin')->logout();

    $response = $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']));

    $response->assertRedirect();
    // Released back to 0 by the cancellation above, then incremented once
    // by this second, legitimate redemption — not 2, since release()
    // decrements used_count exactly as it does for a logged-in customer.
    expect($coupon->refresh()->used_count)->toBe(1);
});

test('a back-to-back attempt right at the global limit boundary is cleanly rejected, not double-counted', function () {
    // Sequential, not truly concurrent (SQLite is single-writer) — this
    // proves the locked re-check at consumption time correctly stops the
    // count from exceeding max_uses, which is the part under our control.
    // It does not by itself prove MySQL row-lock behavior under genuine
    // parallel connections; that's verified by inspection (lockForUpdate()
    // inside DB::transaction(), the same primitive already used for stock
    // and flash-sale caps) rather than a race test.
    $coupon = Coupon::factory()->create(['max_uses' => 1, 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Cart::query()->where('user_id', $userA->id)->delete();
    $this->actingAs($userA)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))->assertRedirect();

    Cart::query()->where('user_id', $userB->id)->delete();
    $this->actingAs($userB)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertSessionHasErrors('coupon_code');

    expect($coupon->refresh()->used_count)->toBe(1);
    expect(Order::query()->count())->toBe(1);
});

test('a COD checkout that fails on insufficient stock never increments usage', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 1]);

    Cart::query()->where('user_id', $user->id)->delete();
    $response = $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 5, $coupon->code));

    $response->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);
    expect($coupon->refresh()->used_count)->toBe(0);
});

test('an expired, never-confirmed mobile-banking pending checkout never consumed usage in the first place', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['max_uses' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    // Coupon was validated/previewed but nothing was ever incremented —
    // consumption only happens at actual order creation.
    expect($coupon->refresh()->used_count)->toBe(0);

    $pending = PendingCheckout::query()->firstOrFail();
    $pending->update(['expires_at' => now()->subHour()]);

    // The customer never confirms; the PendingCheckout simply expires.
    // Nothing needs to release anything, because nothing was reserved.
    expect($coupon->refresh()->used_count)->toBe(0);

    // A different customer can still fully use the coupon.
    $otherUser = User::factory()->create();
    Cart::query()->where('user_id', $otherUser->id)->delete();
    $this->actingAs($otherUser)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);
});

test('confirming a mobile-banking payment consumes usage at confirmation time, matching the quoted total', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 15]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(0);
    $pending = PendingCheckout::query()->firstOrFail();
    expect((float) $pending->checkout_data['discount'])->toBe(15.0);

    $sms = ReceivedSms::create([
        'sender' => '01700000000', 'provider' => 'bkash', 'content' => 'test', 'received_at' => now(),
        'parsed_transaction_id' => 'TXNCOUPON1', 'parsed_amount' => $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($coupon->refresh()->used_count)->toBe(1);
    expect((float) $order->discount)->toBe(15.0);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeTrue();
});

test('a duplicate/retried payment confirmation never consumes usage twice', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    $pending = PendingCheckout::query()->firstOrFail();
    $sms = ReceivedSms::create([
        'sender' => '01700000000', 'provider' => 'bkash', 'content' => 'test', 'received_at' => now(),
        'parsed_transaction_id' => 'TXNDUP1', 'parsed_amount' => $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);

    // Customer double-submits (or a retried callback hits the same token) —
    // the existing order_number-set guard returns the existing order
    // without re-running any of the creation side effects.
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ]);

    expect($coupon->refresh()->used_count)->toBe(1);
    expect(Order::query()->count())->toBe(1);
});

test('cancelling an order releases its coupon usage exactly once', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-coupon@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($coupon->refresh()->used_count)->toBe(1);

    auth('web')->logout();
    actingAs($admin, 'admin');
    $this->patch(route('admin.orders.cancel', $order), ['reason' => 'test'])->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(0);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeFalse();
    expect($order->refresh()->coupon_released_at)->not->toBeNull();
});

test('an order with no coupon is unaffected by cancellation release logic', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-nocoupon@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'No Coupon', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->coupon_id)->toBeNull();

    auth('web')->logout();
    actingAs($admin, 'admin');
    $this->patch(route('admin.orders.cancel', $order), ['reason' => 'test'])->assertRedirect();

    expect($order->refresh()->coupon_released_at)->toBeNull();
});

test('a coupon discount stacks correctly on top of an already flash-sale-discounted subtotal', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);
    $coupon = Coupon::factory()->create(['type' => 'percent', 'value' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 2, $coupon->code))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();
    // Subtotal uses the flash price (150 x 2 = 300); coupon then takes 10% off that (30).
    expect((float) $order->subtotal)->toBe(300.0);
    expect((float) $order->discount)->toBe(30.0);
    expect($coupon->refresh()->used_count)->toBe(1);
});

test('marking an order refunded (not just cancelled) also releases its coupon usage', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-refund-coupon@example.com', 'password' => bcrypt('x')]);
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($coupon->refresh()->used_count)->toBe(1);

    auth('web')->logout();
    actingAs($admin, 'admin');
    // PAY-RETURN-001: only a collected payment can be refunded — treat it as paid online, never shipped.
    $order->forceFill(['payment_method' => 'mobile_banking', 'payment_status' => 'paid'])->save();
    $this->patch(route('admin.orders.markRefunded', $order), wholeOrderRefundPayload($order))->assertSessionHas('success');

    expect($coupon->refresh()->used_count)->toBe(0);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeFalse();
});

test('releasing the same orders coupon usage twice directly does not double-decrement', function () {
    $coupon = Coupon::factory()->create(['used_count' => 1]);
    $order = Order::create([
        'order_number' => 'ORD-RELEASE-TWICE', 'status' => OrderStatus::CANCELLED, 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 10, 'tax' => 0, 'total' => 90,
        'coupon_id' => $coupon->id, 'coupon_code' => $coupon->code,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => User::factory()->create()->id, 'order_id' => $order->id, 'discount_amount' => 10]);

    $service = app(CouponService::class);
    $service->release($order->fresh());
    $service->release($order->fresh());

    expect($coupon->refresh()->used_count)->toBe(0);
});

test('releasing one orders coupon usage never touches another orders CouponUse rows or coupon', function () {
    $couponA = Coupon::factory()->create(['used_count' => 1]);
    $couponB = Coupon::factory()->create(['used_count' => 1]);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $orderA = Order::create([
        'order_number' => 'ORD-ISOLATE-A', 'status' => OrderStatus::CANCELLED, 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 10, 'tax' => 0, 'total' => 90,
        'coupon_id' => $couponA->id, 'coupon_code' => $couponA->code,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    $orderB = Order::create([
        'order_number' => 'ORD-ISOLATE-B', 'status' => OrderStatus::DELIVERED, 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 10, 'tax' => 0, 'total' => 90,
        'coupon_id' => $couponB->id, 'coupon_code' => $couponB->code,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    CouponUse::create(['coupon_id' => $couponA->id, 'user_id' => $userA->id, 'order_id' => $orderA->id, 'discount_amount' => 10]);
    $useB = CouponUse::create(['coupon_id' => $couponB->id, 'user_id' => $userB->id, 'order_id' => $orderB->id, 'discount_amount' => 10]);

    app(CouponService::class)->release($orderA->fresh());

    expect($couponA->refresh()->used_count)->toBe(0);
    expect($couponB->refresh()->used_count)->toBe(1);
    expect(CouponUse::find($useB->id))->not->toBeNull();
});

test('a coupon that gets exhausted by another order between mobile-banking initiation and confirmation fails cleanly, leaving the SMS recoverable', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['max_uses' => 1, 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    $pending = PendingCheckout::query()->firstOrFail();

    // A different COD order consumes the coupon's only slot before this
    // customer gets around to confirming their mobile-banking payment.
    $otherUser = User::factory()->create();
    Cart::query()->where('user_id', $otherUser->id)->delete();
    $this->actingAs($otherUser)->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code))
        ->assertRedirect();
    expect($coupon->refresh()->used_count)->toBe(1);

    $sms = ReceivedSms::create([
        'sender' => '01700000000', 'provider' => 'bkash', 'content' => 'test', 'received_at' => now(),
        'parsed_transaction_id' => 'TXNEXHAUST1', 'parsed_amount' => $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $response = $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ]);

    $response->assertSessionHasErrors('transaction_id');
    expect(Order::where('coupon_id', $coupon->id)->count())->toBe(1); // only the other order
    expect($coupon->refresh()->used_count)->toBe(1); // not incremented further
    expect($sms->refresh()->is_matched)->toBeFalse(); // paid-but-unfulfilled, recoverable

    // Once resolved (coupon removed/replaced by support), the same SMS can still be matched.
    $pending->update(['checkout_data' => array_merge($pending->checkout_data, ['coupon_id' => null, 'discount' => 0, 'total' => $pending->checkout_data['subtotal'] + $pending->checkout_data['shipping_cost']])]);
    $sms->update(['parsed_amount' => $pending->checkout_data['total']]);
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    expect(Order::where('user_id', $user->id)->exists())->toBeTrue();
});

test('a guest is blocked by the global usage limit even with a different phone number than the first use', function () {
    // PROMO-001 follow-up: this test previously asserted guests had "no
    // identity to track" and relied solely on the global max_uses limit
    // — that was the bug. Guests are now tracked by checkout phone (see
    // the PROMO-001 tests above), so this test is rewritten to isolate
    // what it originally meant to prove: the *global* limit still
    // applies on top of per-guest tracking, using a genuinely different
    // phone for the second attempt so the per-guest check can't be what
    // blocks it.
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['max_uses' => 1, 'per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01711111111']))
        ->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);
    expect(CouponUse::where('coupon_id', $coupon->id)->exists())->toBeFalse(); // guests never get a CouponUse row

    $response = $this->post(route('checkout.store'), couponCheckoutPayload($product, 1, $coupon->code, ['phone' => '01722222222']));
    $response->assertSessionHasErrors('coupon_code');
    expect($coupon->refresh()->used_count)->toBe(1);
});
