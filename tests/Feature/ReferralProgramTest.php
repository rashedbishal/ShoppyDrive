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
use App\Services\ReferralService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function enableReferralProgram(array $overrides = []): void
{
    $defaults = [
        'referral_enabled' => true,
        'referral_discount_type' => 'percent',
        'referral_discount_value' => 10,
        'referral_min_order_amount' => 0,
        'referral_reward_points' => 50,
        'referral_reward_delay_days' => 0,
        'referral_unlock_order_count' => 0,
        'referral_reward_cap_amount' => 0,
        'referral_reward_cap_period' => 'month',
        'points_redeem_value_bdt' => 1,
        'points_redeem_max_percent' => 50,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
    }
}

/** Places and delivers a qualifying order for $referred, backdating delivery so it's immediately due. */
function placeAndDeliverQualifyingOrder(User $referred, Product $product, int $daysAgo = 1): Order
{
    Cart::query()->where('user_id', $referred->id)->delete();

    test()->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $referred->id)->latest()->firstOrFail();
    $order->update(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()->subDays($daysAgo)]);

    return $order->refresh();
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

test('an order below the minimum amount gets no referral discount and leaves eligibility untouched', function () {
    enableReferralProgram(['referral_min_order_amount' => 500]);
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

    $product = Product::factory()->create(['price' => 400, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $referred->id)->delete();

    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $referred->id)->firstOrFail();
    expect($order->used_referral_discount)->toBeFalse();
    expect($order->discount)->toBe(0.0);

    $referred->refresh();
    expect($referred->referral_discount_used_order_id)->toBeNull();

    // A later, qualifying order can still use the discount.
    Cart::query()->where('user_id', $referred->id)->delete();
    $bigProduct = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $bigProduct->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $secondOrder = Order::where('user_id', $referred->id)->where('id', '!=', $order->id)->firstOrFail();
    expect($secondOrder->used_referral_discount)->toBeTrue();
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

// ── Invite link unlock ───────────────────────────────────────────────────────

test('the invite link is locked until the user has enough delivered orders', function () {
    enableReferralProgram(['referral_unlock_order_count' => 2]);
    $user = User::factory()->create();

    $service = app(ReferralService::class);
    expect($service->hasUnlockedInviteLink($user))->toBeFalse();

    for ($i = 0; $i < 2; $i++) {
        Order::create([
            'order_number' => 'ORD-UNLOCK-'.$i,
            'user_id' => $user->id,
            'status' => OrderStatus::DELIVERED,
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal' => 500,
            'shipping_cost' => 60,
            'discount' => 0,
            'tax' => 0,
            'total' => 560,
            'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
            'billing_address' => [],
        ]);
    }

    expect($service->hasUnlockedInviteLink($user->refresh()))->toBeTrue();
});

// ── Delayed referral reward ──────────────────────────────────────────────────

test('placing a qualifying order does not instantly award the referrer any points', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $referred->id)->delete();
    $this->actingAs($referred)->post(route('checkout.store'), codPayload([
        'email' => $referred->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect($referrer->refresh()->points_balance)->toBe(0);
    expect(ReferralPointTransaction::where('type', PointTransactionType::EARN_REFERRAL)->count())->toBe(0);
});

test('the referrer earns points once the delivered order has sat past the reward delay', function () {
    enableReferralProgram(['referral_reward_delay_days' => 7]);
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    // Delivered yesterday — not due yet.
    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(0);

    // Delivered 8 days ago — now due.
    $order->update(['delivered_at' => now()->subDays(8)]);
    artisan('rewards:grant-due');

    $referrer->refresh();
    expect($referrer->points_balance)->toBe(50);
    expect($order->refresh()->referral_reward_granted_at)->not->toBeNull();

    $this->assertDatabaseHas('referral_point_transactions', [
        'user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'order_id' => $order->id,
        'type' => PointTransactionType::EARN_REFERRAL->value,
        'points' => 50,
    ]);

    // Running the grant command again must not double-credit.
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(50);
});

test('a reward that would exceed the per-period cap is held, not granted, until the next period', function () {
    enableReferralProgram(['referral_reward_points' => 50, 'points_redeem_value_bdt' => 1, 'referral_reward_cap_amount' => 60]);
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referredA = User::factory()->create(['referred_by_id' => $referrer->id]);
    $referredB = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $orderA = placeAndDeliverQualifyingOrder($referredA, $product, daysAgo: 1);
    $orderB = placeAndDeliverQualifyingOrder($referredB, $product, daysAgo: 1);

    artisan('rewards:grant-due');

    // 50 BDT-equivalent granted for the first order; the second (another 50)
    // would push the referrer to 100, over the 60 cap, so it's held.
    expect($referrer->refresh()->points_balance)->toBe(50);
    expect($orderA->refresh()->referral_reward_granted_at)->not->toBeNull();
    expect($orderB->refresh()->referral_reward_granted_at)->toBeNull();
});

test('cancelling a qualifying order before its reward is granted restores eligibility but credits nothing', function () {
    enableReferralProgram(['referral_reward_delay_days' => 7]);
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    $order->update(['status' => OrderStatus::CANCELLED]);

    expect($referrer->refresh()->points_balance)->toBe(0);
    expect($referred->refresh()->referral_discount_used_order_id)->toBeNull();

    // Even once due, a cancelled order is never granted (no longer DELIVERED).
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(0);
});

test('cancelling a qualifying order after its reward was already granted revokes the referrers points', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(50);

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

test('redemption is capped at the configured percentage of the subtotal, even if the customer has more points', function () {
    enableReferralProgram(['points_redeem_max_percent' => 20]);
    $customer = User::factory()->create(['points_balance' => 500]);

    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $customer->id)->delete();

    $this->actingAs($customer)->post(route('checkout.store'), codPayload([
        'email' => $customer->email,
        'redeem_points' => true,
        'points_to_redeem' => 500,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $customer->id)->firstOrFail();
    // 20% of 1000 = 200, at ৳1/point that's 200 points, not the requested 500.
    expect($order->points_redeemed)->toBe(200);
    expect($order->discount)->toBe(200.0);
    expect($customer->refresh()->points_balance)->toBe(300);
});

// ── Mobile banking path ─────────────────────────────────────────────────────

test('a qualifying order placed via mobile banking still only rewards the referrer once due', function () {
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
    expect($referrer->refresh()->points_balance)->toBe(0);

    $order->update(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()->subDay()]);
    artisan('rewards:grant-due');

    expect($referrer->refresh()->points_balance)->toBe(50);
});

// ── Admin settings ───────────────────────────────────────────────────────────

test('admin can update referral and loyalty settings', function () {
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
        'referral_min_order_amount' => 300,
        'referral_reward_points' => 25,
        'referral_reward_delay_days' => 5,
        'referral_unlock_order_count' => 2,
        'referral_reward_cap_amount' => 400,
        'referral_reward_cap_period' => 'week',
        'loyalty_earn_rate_bdt_per_point' => 100,
        'loyalty_earn_delay_days' => 3,
        'registration_bonus_points' => 10,
        'review_bonus_points' => 5,
        'daily_login_base_points' => 1,
        'daily_login_cap_days' => 14,
        'points_redeem_value_bdt' => 2,
        'points_redeem_max_percent' => 40,
    ]);

    $response->assertRedirect();

    expect(Setting::find('referral_enabled')?->value)->toBeTrue();
    expect(Setting::find('referral_discount_type')?->value)->toBe('fixed');
    expect((float) Setting::find('referral_discount_value')?->value)->toBe(75.0);
    expect((float) Setting::find('referral_min_order_amount')?->value)->toBe(300.0);
    expect((int) Setting::find('referral_reward_points')?->value)->toBe(25);
    expect((int) Setting::find('referral_reward_delay_days')?->value)->toBe(5);
    expect((int) Setting::find('referral_unlock_order_count')?->value)->toBe(2);
    expect((float) Setting::find('referral_reward_cap_amount')?->value)->toBe(400.0);
    expect(Setting::find('referral_reward_cap_period')?->value)->toBe('week');
    expect((float) Setting::find('loyalty_earn_rate_bdt_per_point')?->value)->toBe(100.0);
    expect((int) Setting::find('loyalty_earn_delay_days')?->value)->toBe(3);
    expect((int) Setting::find('registration_bonus_points')?->value)->toBe(10);
    expect((int) Setting::find('review_bonus_points')?->value)->toBe(5);
    expect((int) Setting::find('daily_login_base_points')?->value)->toBe(1);
    expect((int) Setting::find('daily_login_cap_days')?->value)->toBe(14);
    expect((float) Setting::find('points_redeem_value_bdt')?->value)->toBe(2.0);
    expect((float) Setting::find('points_redeem_max_percent')?->value)->toBe(40.0);
});
