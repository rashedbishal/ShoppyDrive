<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyPointsService;

use function Pest\Laravel\artisan;

function setLoyaltySettings(array $overrides = []): void
{
    $defaults = [
        'loyalty_earn_rate_bdt_per_point' => 150,
        'loyalty_earn_delay_days' => 0,
        'registration_bonus_points' => 20,
        'review_bonus_points' => 15,
        'daily_login_base_points' => 2,
        'daily_login_cap_days' => 30,
        'points_redeem_value_bdt' => 1,
        'points_redeem_max_percent' => 50,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
    }
}

function makeDeliveredOrder(User $user, float $subtotal, float $discount = 0, int $daysAgo = 1): Order
{
    return Order::create([
        'order_number' => 'ORD-LOYALTY-'.uniqid(),
        'user_id' => $user->id,
        'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => $subtotal,
        'shipping_cost' => 120,
        'discount' => $discount,
        'tax' => 0,
        'total' => $subtotal - $discount + 120,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        'delivered_at' => now()->subDays($daysAgo),
    ]);
}

// ── Registration bonus ───────────────────────────────────────────────────────

test('a new customer is credited the registration bonus once', function () {
    setLoyaltySettings();
    $user = User::factory()->create(['points_balance' => 0]);

    $loyalty = app(LoyaltyPointsService::class);
    $loyalty->grantRegistrationBonus($user);
    $loyalty->grantRegistrationBonus($user); // idempotent — a retry must not double-credit

    expect($user->refresh()->points_balance)->toBe(20);
    expect(ReferralPointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::EARN_REGISTRATION)
        ->count())->toBe(1);
});

test('registering through the normal signup form grants the registration bonus', function () {
    setLoyaltySettings();

    $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'bonus-signup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'bonus-signup@example.com')->firstOrFail();
    expect($user->points_balance)->toBe(20);
});

// ── Review bonus ─────────────────────────────────────────────────────────────

test('a review bonus is only granted for an approved, verified-purchase review', function () {
    setLoyaltySettings();
    $user = User::factory()->create(['points_balance' => 0]);
    $product = Product::factory()->create();
    $loyalty = app(LoyaltyPointsService::class);

    // Approved but no linked order — not a verified purchase.
    $unverified = Review::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'order_id' => null,
        'rating' => 5,
        'body' => 'Great product, highly recommend it to everyone.',
        'is_approved' => true,
    ]);
    $loyalty->grantReviewBonus($unverified);
    expect($user->refresh()->points_balance)->toBe(0);

    // Verified purchase, but not yet approved.
    $order = makeDeliveredOrder($user, 1000);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    $pendingApproval = Review::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'order_id' => $order->id,
        'rating' => 5,
        'body' => 'Great product, highly recommend it to everyone.',
        'is_approved' => false,
    ]);
    $loyalty->grantReviewBonus($pendingApproval);
    expect($user->refresh()->points_balance)->toBe(0);

    // Approved AND a verified purchase — grants the bonus, once.
    $pendingApproval->update(['is_approved' => true]);
    $loyalty->grantReviewBonus($pendingApproval->refresh());
    $loyalty->grantReviewBonus($pendingApproval->refresh());

    expect($user->refresh()->points_balance)->toBe(15);
    expect(ReferralPointTransaction::where('type', PointTransactionType::EARN_REVIEW)->count())->toBe(1);
});

test('submitting a review through the controller resolves verified-purchase status automatically', function () {
    setLoyaltySettings();
    $user = User::factory()->create(['points_balance' => 0]);
    $product = Product::factory()->create();

    $order = makeDeliveredOrder($user, 1000);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    $this->actingAs($user)->post(route('product.reviews.store', $product), [
        'rating' => 5,
        'body' => 'Great product, highly recommend it to everyone.',
    ]);

    $review = Review::where('user_id', $user->id)->where('product_id', $product->id)->firstOrFail();
    expect($review->order_id)->toBe($order->id);
    expect($user->refresh()->points_balance)->toBe(15);
});

// ── Order-spend points ───────────────────────────────────────────────────────

test('spend-based points are not granted until the delivered order clears the earn delay', function () {
    setLoyaltySettings(['loyalty_earn_delay_days' => 7]);
    $user = User::factory()->create(['points_balance' => 0]);

    $order = makeDeliveredOrder($user, 1000, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($user->refresh()->points_balance)->toBe(0);

    $order->update(['delivered_at' => now()->subDays(8)]);
    artisan('rewards:grant-due');
    expect($user->refresh()->points_balance)->toBe((int) ceil(1000 / 150));
    expect($order->refresh()->loyalty_points_granted_at)->not->toBeNull();

    // Idempotent — running it again must not double-credit.
    artisan('rewards:grant-due');
    expect($user->refresh()->points_balance)->toBe((int) ceil(1000 / 150));
});

test('spend-based points round up and exclude both delivery charge and discount', function () {
    setLoyaltySettings(['loyalty_earn_rate_bdt_per_point' => 150]);
    $user = User::factory()->create(['points_balance' => 0]);

    // subtotal 1000, discount 900 → net spend 100 → 100/150 = 0.667 → rounds up to 1.
    makeDeliveredOrder($user, 1000, discount: 900);
    artisan('rewards:grant-due');

    expect($user->refresh()->points_balance)->toBe(1);
});

test('cancelling a delivered order after its spend points were granted does not revoke them', function () {
    // Spend-based earn points are a flat loyalty reward, not a referral-specific
    // one — only the referral reward and redeemed points are reversed on
    // cancellation, matching the spec (only the referral reward is return-gated).
    setLoyaltySettings();
    $user = User::factory()->create(['points_balance' => 0]);

    $order = makeDeliveredOrder($user, 1000);
    artisan('rewards:grant-due');
    $balanceAfterGrant = $user->refresh()->points_balance;
    expect($balanceAfterGrant)->toBeGreaterThan(0);

    $order->update(['status' => OrderStatus::CANCELLED]);
    expect($user->refresh()->points_balance)->toBe($balanceAfterGrant);
});

// ── Daily login streak ───────────────────────────────────────────────────────

test('the daily login streak increments on consecutive days and resets after a missed day', function () {
    setLoyaltySettings(['daily_login_base_points' => 2, 'daily_login_cap_days' => 30]);
    $user = User::factory()->create(['points_balance' => 0]);
    $loyalty = app(LoyaltyPointsService::class);

    $day1 = $loyalty->claimDailyLogin($user->refresh());
    expect($day1)->toMatchArray(['streakDay' => 1, 'points' => 2, 'awarded' => true]);

    // Claiming again the same day is a no-op, not a second grant.
    $again = $loyalty->claimDailyLogin($user->refresh());
    expect($again)->toMatchArray(['streakDay' => 1, 'claimedToday' => true, 'awarded' => false]);
    expect($user->refresh()->points_balance)->toBe(2);

    // "Tomorrow" — streak continues to day 2 (2 × 2 = 4 points).
    $user->forceFill(['login_streak_date' => now('Asia/Dhaka')->subDay()->toDateString()])->save();
    $day2 = $loyalty->claimDailyLogin($user->refresh());
    expect($day2)->toMatchArray(['streakDay' => 2, 'points' => 4, 'awarded' => true]);
    expect($user->refresh()->points_balance)->toBe(6);

    // A missed day (last claim 2 days ago) resets the streak back to day 1.
    $user->forceFill(['login_streak_date' => now('Asia/Dhaka')->subDays(2)->toDateString()])->save();
    $day3 = $loyalty->claimDailyLogin($user->refresh());
    expect($day3)->toMatchArray(['streakDay' => 1, 'points' => 2, 'awarded' => true]);
});

test('the daily login reward stops growing past the streak cap', function () {
    setLoyaltySettings(['daily_login_base_points' => 2, 'daily_login_cap_days' => 3]);
    $user = User::factory()->create([
        'points_balance' => 0,
        'login_streak_count' => 3,
        'login_streak_date' => now('Asia/Dhaka')->subDay()->toDateString(),
    ]);
    $loyalty = app(LoyaltyPointsService::class);

    // Day 4 would be 4 × 2 = 8 uncapped, but the cap holds it at 3 × 2 = 6.
    $result = $loyalty->claimDailyLogin($user->refresh());
    expect($result)->toMatchArray(['streakDay' => 4, 'points' => 6, 'awarded' => true]);
});

test('claiming the daily login bonus through the account endpoint updates the balance', function () {
    setLoyaltySettings(['daily_login_base_points' => 3]);
    $user = User::factory()->create(['points_balance' => 0]);

    $response = $this->actingAs($user)->postJson(route('account.referral.daily-login'));

    $response->assertOk()->assertJson(['streakDay' => 1, 'points' => 3, 'awarded' => true]);
    expect($user->refresh()->points_balance)->toBe(3);

    // A second claim the same day awards nothing more.
    $this->actingAs($user)->postJson(route('account.referral.daily-login'))
        ->assertOk()->assertJson(['awarded' => false]);
    expect($user->refresh()->points_balance)->toBe(3);
});
