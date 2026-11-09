<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function validateCoupon(string $code, float $subtotal = 100, ?string $phone = null): TestResponse
{
    return test()->postJson(route('coupon.validate'), array_filter([
        'code' => $code,
        'subtotal' => $subtotal,
        'phone' => $phone,
    ], fn ($v) => $v !== null));
}

function guestOrderWithCoupon(Coupon $coupon, string $phone): Order
{
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    return Order::create([
        'order_number' => 'ORD-PREVIEW-'.uniqid(),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'coupon_id' => $coupon->id,
        'subtotal' => 100,
        'shipping_cost' => 0,
        'discount' => 10,
        'tax' => 0,
        'total' => 90,
        'shipping_address' => ['phone' => $phone],
        'billing_address' => [],
    ]);
}

test('a guest with no previous redemption gets a successful preview', function () {
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);

    $response = validateCoupon($coupon->code, 100, '01711111111');

    $response->assertOk();
    expect($response->json('coupon.id'))->toBe($coupon->id);
});

test('a guest whose phone already redeemed the coupon gets the same specific reason a logged-in customer would', function () {
    // BIZ-015 (owner decision, 2026-11-09): previously a guest got a
    // deliberately vague rejection here to avoid letting a coupon code
    // holder probe arbitrary phone numbers and learn which ones had used
    // it (an enumeration risk). The owner chose clearer guest messaging
    // over closing that risk, so a guest now sees the exact same reason
    // eligibilityError() already gave logged-in customers.
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    guestOrderWithCoupon($coupon, '01711111111');

    $response = validateCoupon($coupon->code, 100, '01711111111');

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('You have already used this coupon the maximum number of times.');
});

test('invalid phone formats are treated as if no phone were submitted, not rejected', function () {
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    guestOrderWithCoupon($coupon, '01711111111');

    foreach ([
        '01711 111111',     // space
        '+8801711111111',   // country code
        '017-1111-1111',    // dashes
        '01711111',         // too short
        'not-a-phone',       // garbage
    ] as $malformed) {
        // Same phone number that WOULD be blocked in canonical form, but
        // submitted malformed — must not 422 on format, and must not
        // trigger the guest redemption check at all (silently skipped,
        // same as omitted), so the preview still succeeds.
        $response = validateCoupon($coupon->code, 100, $malformed);

        $response->assertOk("Expected success for malformed phone '{$malformed}', got: ".$response->getContent());
    }
});

test('omitting the phone parameter entirely preserves existing behavior', function () {
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    guestOrderWithCoupon($coupon, '01711111111');

    $response = validateCoupon($coupon->code, 100);

    $response->assertOk();
});

test('a logged-in customer still gets the informative per-user-limit message, unaffected by guest phone logic', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => guestOrderWithCoupon($coupon, '01799999999')->id, 'discount_amount' => 10]);

    $response = test()->actingAs($user)->postJson(route('coupon.validate'), [
        'code' => $coupon->code,
        'subtotal' => 100,
    ]);

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('You have already used this coupon the maximum number of times.');
});

test('a logged-in customer sending a phone parameter does not trigger the guest check', function () {
    // Defensive: a logged-in user's own request should never be routed
    // through the guest-identity path, regardless of what's in the body.
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    guestOrderWithCoupon($coupon, '01711111111');

    $response = test()->actingAs($user)->postJson(route('coupon.validate'), [
        'code' => $coupon->code,
        'subtotal' => 100,
        'phone' => '01711111111',
    ]);

    $response->assertOk();
});

test('an invalid coupon code still returns its own distinct message, not the generic guest rejection', function () {
    $response = validateCoupon('DOES-NOT-EXIST', 100, '01711111111');

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('Invalid coupon code.');
});

test('real checkout submission still correctly blocks guest reuse regardless of the preview response', function () {
    // Regression guard: the preview's genericized/skipped guest check
    // must never leak into the authoritative path.
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['per_user_limit' => 1, 'type' => 'fixed', 'value' => 10]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    test()->post(route('checkout.store'), [
        'name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '01711111111',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod', 'coupon_code' => $coupon->code,
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertRedirect();

    expect($coupon->refresh()->used_count)->toBe(1);

    $response = test()->post(route('checkout.store'), [
        'name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '01711111111',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod', 'coupon_code' => $coupon->code,
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]);

    $response->assertSessionHasErrors('coupon_code');
    expect($coupon->refresh()->used_count)->toBe(1);
});
