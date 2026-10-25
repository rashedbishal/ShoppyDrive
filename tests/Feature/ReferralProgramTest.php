<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function enableReferralProgram(array $overrides = []): void
{
    $defaults = [
        'referral_enabled' => true,
        'referral_discount_type' => 'percent',
        'referral_discount_value' => 10,
        'referral_points_per_referral' => 50,
        'referral_points_value' => 1,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
    }
}

function codPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
    ], $overrides);
}

// ── Referral code generation ────────────────────────────────────────────────

test('every new user is given a unique referral code', function () {
    $user = User::factory()->create();

    expect($user->referral_code)->not->toBeNull();
    expect(strlen($user->referral_code))->toBe(12);
});

// ── Registration attribution ────────────────────────────────────────────────

test('registering with a valid referral code links the new user to the referrer', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();

    $response = $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'new-customer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => $referrer->referral_code,
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'new-customer@example.com')->firstOrFail();
    expect($newUser->referred_by_id)->toBe($referrer->id);
});

test('registering with an unknown referral code does not fail registration', function () {
    enableReferralProgram();

    $response = $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'unknown-ref@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => 'NOTAREALCODE',
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'unknown-ref@example.com')->firstOrFail();
    expect($newUser->referred_by_id)->toBeNull();
});

test('referral links create no attribution while the program is disabled', function () {
    // referral_enabled defaults to false/missing — do not call enableReferralProgram().
    $referrer = User::factory()->create();

    $response = $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'disabled-program@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => $referrer->referral_code,
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'disabled-program@example.com')->firstOrFail();
    expect($newUser->referred_by_id)->toBeNull();
});

// ── Registration page: referral code validity ───────────────────────────────

test('visiting the register page with a valid referral code flags it as valid', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();

    $response = $this->get(route('register', ['ref' => $referrer->referral_code]));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/register')
        ->where('referralCode', $referrer->referral_code)
        ->where('referralCodeValid', true)
    );
});

test('visiting the register page with an unknown referral code flags it as invalid, not silently valid', function () {
    enableReferralProgram();

    $response = $this->get(route('register', ['ref' => 'NOTAREALCODE']));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/register')
        ->where('referralCode', 'NOTAREALCODE')
        ->where('referralCodeValid', false)
    );
});

test('visiting the register page without a referral code sends neither a code nor a validity flag', function () {
    enableReferralProgram();

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/register')
        ->where('referralCode', null)
        ->where('referralCodeValid', null)
    );
});

test('an unknown referral code on the register page is not stashed in session for attribution', function () {
    enableReferralProgram();

    $this->get(route('register', ['ref' => 'NOTAREALCODE']));

    expect(session('pending_referral_code'))->toBeNull();
});

// ── Checkout discount ────────────────────────────────────────────────────────

test('an eligible referred customer gets the referral discount on their first order but not their second', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $firstOrder = Order::where('user_id', $referred->id)->firstOrFail();
    expect($firstOrder->used_referral_discount)->toBeTrue();
    expect($firstOrder->discount)->toBe(100.0); // 10% of 1000

    $referred->refresh();
    expect($referred->referral_discount_used_order_id)->toBe($firstOrder->id);

    // Second order — no longer eligible.
    Cart::query()->where('user_id', $referred->id)->delete();
    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $secondOrder = Order::where('user_id', $referred->id)->where('id', '!=', $firstOrder->id)->firstOrFail();
    expect($secondOrder->used_referral_discount)->toBeFalse();
    expect($secondOrder->discount)->toBe(0.0);
});

test('a coupon code takes precedence over the referral discount', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $coupon = Coupon::create([
        'code' => 'SAVE50',
        'name' => 'Save 50',
        'type' => 'fixed',
        'value' => 50,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'coupon_code' => 'SAVE50',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $referred->id)->firstOrFail();
    expect($order->used_referral_discount)->toBeFalse();
    expect($order->coupon_id)->toBe($coupon->id);
    expect($order->discount)->toBe(50.0);

    // Eligibility is untouched since the referral discount never fired.
    $referred->refresh();
    expect($referred->referral_discount_used_order_id)->toBeNull();
});

// ── Points earning / revocation ─────────────────────────────────────────────

test('the referrer earns points once the referred customers qualifying order is placed', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $referred->id)->firstOrFail();

    $referrer->refresh();
    expect($referrer->points_balance)->toBe(50);

    $this->assertDatabaseHas('referral_point_transactions', [
        'user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'order_id' => $order->id,
        'type' => PointTransactionType::EARN->value,
        'points' => 50,
    ]);
});

test('cancelling the qualifying order revokes the referrers points and restores the referred users eligibility', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $referred->id)->firstOrFail();
    $referrer->refresh();
    expect($referrer->points_balance)->toBe(50);

    $order->update(['status' => OrderStatus::CANCELLED]);

    $referrer->refresh();
    $referred->refresh();
    expect($referrer->points_balance)->toBe(0);
    expect($referred->referral_discount_used_order_id)->toBeNull();

    $this->assertDatabaseHas('referral_point_transactions', [
        'order_id' => $order->id,
        'type' => PointTransactionType::REVOKE->value,
        'points' => -50,
    ]);

    // A second transition through a cancelled/refunded status must not
    // revoke a second time (there's nothing left to revoke, floor at 0).
    $order->update(['status' => OrderStatus::REFUNDED]);
    $referrer->refresh();
    expect($referrer->points_balance)->toBe(0);
});

// ── Points redemption ────────────────────────────────────────────────────────

test('a customer can redeem points at checkout, and cancelling that order refunds the points', function () {
    enableReferralProgram();
    $customer = User::factory()->create(['points_balance' => 100]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $customer->id)->delete();

    $this->actingAs($customer)->post(route('checkout.store'), codPayload([
        'email' => $customer->email,
        'redeem_points' => true,
        'points_to_redeem' => 60,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $customer->id)->firstOrFail();
    expect($order->points_redeemed)->toBe(60);
    expect($order->discount)->toBe(60.0); // 60 points * ৳1/point

    $customer->refresh();
    expect($customer->points_balance)->toBe(40);

    $order->update(['status' => OrderStatus::CANCELLED]);

    $customer->refresh();
    expect($customer->points_balance)->toBe(100);
    expect($order->refresh()->points_refunded_at)->not->toBeNull();

    // Refunding twice must not double-credit.
    $order->update(['status' => OrderStatus::REFUNDED]);
    $customer->refresh();
    expect($customer->points_balance)->toBe(100);
});

test('points balance always equals the sum of that users ledger transactions', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $referrer->refresh();
    $sum = ReferralPointTransaction::where('user_id', $referrer->id)->sum('points');
    expect($sum)->toBe($referrer->points_balance);
});

// ── Mobile banking path ─────────────────────────────────────────────────────

test('the referrer earns points when the qualifying order is placed via mobile banking', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'payment_method' => 'mobile_banking',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $pending = PendingCheckout::where('user_id', $referred->id)->firstOrFail();
    expect($pending->checkout_data['used_referral_discount'])->toBeTrue();

    $sms = ReceivedSms::create([
        'sender' => '01700000000',
        'provider' => 'bkash',
        'content' => 'test sms',
        'received_at' => now(),
        'parsed_transaction_id' => 'TXN123456',
        'parsed_amount' => $pending->checkout_data['total'],
        'is_matched' => false,
    ]);

    $this->actingAs($referred)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash',
        'transaction_id' => $sms->parsed_transaction_id,
    ]);

    $order = Order::where('user_id', $referred->id)->firstOrFail();
    expect($order->used_referral_discount)->toBeTrue();

    $referrer->refresh();
    expect($referrer->points_balance)->toBe(50);
});

// ── Admin settings ───────────────────────────────────────────────────────────

test('admin can update referral settings', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-'.Str::random(6).'@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'referral_enabled' => true,
        'referral_discount_type' => 'fixed',
        'referral_discount_value' => 75,
        'referral_points_per_referral' => 25,
        'referral_points_value' => 2,
    ]);

    $response->assertRedirect();

    expect(Setting::find('referral_enabled')?->value)->toBeTrue();
    expect(Setting::find('referral_discount_type')?->value)->toBe('fixed');
    expect((float) Setting::find('referral_discount_value')?->value)->toBe(75.0);
    expect((int) Setting::find('referral_points_per_referral')?->value)->toBe(25);
    expect((float) Setting::find('referral_points_value')?->value)->toBe(2.0);
});
