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
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

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
        // BIZ-024: separate referred customers have separate phones and
        // delivery addresses; the same phone or address counts as the same
        // person for the referral discount.
        'phone' => sprintf('017%08d', $referred->id),
        'address_line1' => "House {$referred->id}, Road 7",
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
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}

// ── Referral code generation ────────────────────────────────────────────────

test('every new user is given a unique referral code', function () {
    $user = User::factory()->create();

    expect($user->referral_code)->not->toBeNull();
    expect(strlen($user->referral_code))->toBe(12);
});

// ── Registration attribution ────────────────────────────────────────────────

test('registering through a valid referral link links the new user to the referrer', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();

    $this->get(route('register', ['ref' => $referrer->referral_code]));

    $response = $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'new-customer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect();
    $newUser = User::where('email', 'new-customer@example.com')->firstOrFail();
    expect($newUser->referred_by_id)->toBe($referrer->id);
});

function disc12Register(string $email, ?string $code): TestResponse
{
    return test()->post(route('register'), array_filter([
        'name' => 'New Customer',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'referral_code' => $code,
    ], fn ($value) => $value !== null));
}

test('DISC-30: a referral code sent in the form is ignored; only the referral link counts', function (string $code) {
    // DISC-30 (owner, 2026-11-14): the register page has no referral code
    // field; referrals come only from the link. This replaces DISC-12's
    // typed-code validation.
    enableReferralProgram();
    $referrer = User::factory()->create();
    $sent = $code === 'valid' ? $referrer->referral_code : 'NOTAREALCODE';

    disc12Register("sent-{$code}@example.com", $sent)->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', "sent-{$code}@example.com")->firstOrFail()->referred_by_id)->toBeNull();
})->with(['valid', 'unknown']);

test('DISC-30: a sent code never overrides the referral link', function () {
    enableReferralProgram();
    $linkReferrer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    $this->get(route('register', ['ref' => $linkReferrer->referral_code]));
    disc12Register('link-wins@example.com', $otherCustomer->referral_code)->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'link-wins@example.com')->firstOrFail()->referred_by_id)->toBe($linkReferrer->id);
});

test('DISC-12: a lowercase referral link links the right referrer', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();
    User::factory()->create(); // another customer who must not be linked

    $this->get(route('register', ['ref' => strtolower($referrer->referral_code)]));
    disc12Register('lowercase-ref@example.com', null)->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'lowercase-ref@example.com')->firstOrFail()->referred_by_id)->toBe($referrer->id);
});

test('DISC-12: an empty referral field registers normally without a referrer', function (?string $code) {
    enableReferralProgram();

    disc12Register('empty-ref@example.com', $code)->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'empty-ref@example.com')->firstOrFail()->referred_by_id)->toBeNull();
})->with(['empty string' => '', 'spaces only' => '   ', 'field absent' => null]);

test('DISC-12: while the program is off a typed unknown code is ignored and registration succeeds', function () {
    // referral_enabled not set: the program is off and the field isn't shown.
    disc12Register('off-ref@example.com', 'NOTAREALCODE')->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'off-ref@example.com')->firstOrFail()->referred_by_id)->toBeNull();
});

test('DISC-12: an invalid ?ref= link still lets the visitor register without a referral', function () {
    enableReferralProgram();

    // The page still flags the link as invalid (this drives the warning banner)...
    $this->get(route('register', ['ref' => 'NOTAREALCODE']))
        ->assertInertia(fn ($page) => $page->where('referralCode', 'NOTAREALCODE')->where('referralCodeValid', false));

    // ...and registration still succeeds, without a referrer.
    disc12Register('bad-link@example.com', null)->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'bad-link@example.com')->firstOrFail()->referred_by_id)->toBeNull();
});

test('DISC-30: the register page has no referral code field and keeps both referral banners', function () {
    // Source-level check of register.tsx (this project has no JavaScript test runner).
    $text = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/pages/auth/register.tsx')));

    expect($text)->not->toContain('referral_code')
        ->and($text)->toContain('referralCodeValid && (')
        ->and($text)->toContain('referralCodeValid === false && (')
        ->and($text)->toContain('Invalid referral link.')
        ->and($text)->toContain('you can still create an account without one');
});

test('DISC-30: the referred banner still shows when the visitor returns to Register without ?ref=', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();

    $this->get(route('register', ['ref' => $referrer->referral_code]));

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('referralCode', $referrer->referral_code)->where('referralCodeValid', true));
});

test('DISC-30: no referred banner from a saved link code that no longer resolves', function () {
    enableReferralProgram();
    $referrer = User::factory()->create();

    $this->get(route('register', ['ref' => $referrer->referral_code]));
    Setting::updateOrCreate(['key' => 'referral_enabled'], ['value' => '0']);

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('referralCode', null)->where('referralCodeValid', null));
});

test('DISC-30: the account referral page shares the link only, not the bare code', function () {
    $text = (string) file_get_contents(resource_path('js/pages/account/Referral.tsx'));

    expect($text)->not->toContain('Your code')
        ->and($text)->not->toContain('referralCode')
        ->and($text)->toContain('referralLink');
});

test('referral links create no attribution while the program is disabled', function () {
    // referral_enabled defaults to false/missing — do not call enableReferralProgram().
    $referrer = User::factory()->create();

    $this->get(route('register', ['ref' => $referrer->referral_code]));

    $response = $this->post(route('register'), [
        'name' => 'New Customer',
        'email' => 'disabled-program@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
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
    // revoke a second time (the reward is reversed exactly once).
    $order->update(['status' => OrderStatus::REFUNDED]);
    $referrer->refresh();
    expect($referrer->points_balance)->toBe(0);
});

test('reversing a referral reward via two independently loaded stale order copies does not double-revoke', function () {
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(50);

    // Two separate Eloquent instances, both loaded while the referral
    // discount/reward reversal flags are still in their pre-cancellation
    // state — simulating two processes (e.g. a courier webhook racing an
    // admin cancellation) that each read the order before either one had
    // written the reversal.
    $staleCopyOne = Order::find($order->id);
    $staleCopyTwo = Order::find($order->id);

    $staleCopyOne->update(['status' => OrderStatus::CANCELLED]);
    expect($referrer->refresh()->points_balance)->toBe(0);
    expect($referred->refresh()->referral_discount_used_order_id)->toBeNull();

    // $staleCopyTwo still reflects the pre-reversal state in memory. Before
    // the LOYALTY-01 fix, acting on this stale copy would attempt (and,
    // depending on balance, could appear to) revoke a second time; the fix
    // re-checks a freshly locked read instead.
    $staleCopyTwo->update(['status' => OrderStatus::REFUNDED]);
    expect($referrer->refresh()->points_balance)->toBe(0);

    expect(
        ReferralPointTransaction::where('order_id', $order->id)
            ->where('type', PointTransactionType::REVOKE)
            ->count()
    )->toBe(1);
});

test('a reward granted in the first hours of a period still counts towards that period\'s cap', function (string $period, string $firstHours, string $laterInPeriod) {
    enableReferralProgram(['referral_reward_points' => 50, 'points_redeem_value_bdt' => 1, 'referral_reward_cap_amount' => 60, 'referral_reward_cap_period' => $period]);
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referredA = User::factory()->create(['referred_by_id' => $referrer->id]);
    $referredB = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10, 'published_at' => '2026-01-01 00:00:00']); // before the travel dates below

    $this->travelTo($firstHours);
    placeAndDeliverQualifyingOrder($referredA, $product);
    artisan('rewards:grant-due');

    $this->travelTo($laterInPeriod);
    $orderB = placeAndDeliverQualifyingOrder($referredB, $product);
    artisan('rewards:grant-due');

    expect($referrer->refresh()->points_balance)->toBe(50);
    expect($orderB->refresh()->referral_reward_granted_at)->toBeNull();
})->with([
    'monthly, 01:40 Dhaka on the 1st' => ['month', '2026-10-31 19:40:00', '2026-11-15 12:00:00'],
    'weekly, 01:00 Dhaka on Monday' => ['week', '2026-11-01 19:00:00', '2026-11-04 12:00:00'],
]);

test('a reward granted just before a period starts does not count towards the new period\'s cap', function (string $period, string $beforeStart, string $afterStart) {
    enableReferralProgram(['referral_reward_points' => 50, 'points_redeem_value_bdt' => 1, 'referral_reward_cap_amount' => 60, 'referral_reward_cap_period' => $period]);
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referredA = User::factory()->create(['referred_by_id' => $referrer->id]);
    $referredB = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10, 'published_at' => '2026-01-01 00:00:00']); // before the travel dates below

    $this->travelTo($beforeStart);
    placeAndDeliverQualifyingOrder($referredA, $product);
    artisan('rewards:grant-due');

    $this->travelTo($afterStart);
    placeAndDeliverQualifyingOrder($referredB, $product);
    artisan('rewards:grant-due');

    expect($referrer->refresh()->points_balance)->toBe(100);
})->with([
    'monthly, either side of midnight Dhaka on the 1st' => ['month', '2026-10-31 17:30:00', '2026-10-31 18:30:00'],
    'weekly, either side of midnight Dhaka on Monday' => ['week', '2026-11-01 17:30:00', '2026-11-01 18:30:00'],
]);

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
    $admin = createAdmin([
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

test('revoking a referral reward never increases a referrer\'s negative points balance', function () {
    // PAY-RETURN-001: balances may be negative (order-earned points reversed
    // after being spent); the referral revoke must still never add points.
    // DISC-11 (owner rule, 2026-11-13): the full reward is now reversed even
    // from an already-negative balance, so -30 becomes -80 (was: left at -30).
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(50);

    $referrer->forceFill(['points_balance' => -30])->save();
    $order->update(['status' => OrderStatus::CANCELLED]);

    expect($referrer->refresh()->points_balance)->toBe(-80);
});

/** DISC-11: a referrer who earned the 50-point reward and spent 30 of it (balance 20). */
function disc11SpentReward(): array
{
    enableReferralProgram();
    $referrer = User::factory()->create(['points_balance' => 0]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $order = placeAndDeliverQualifyingOrder($referred, $product, daysAgo: 1);
    artisan('rewards:grant-due');
    expect($referrer->refresh()->points_balance)->toBe(50);

    // Spent 30 of the 50 reward points elsewhere.
    $referrer->decrement('points_balance', 30);
    ReferralPointTransaction::create(['user_id' => $referrer->id, 'type' => PointTransactionType::REDEEM, 'points' => -30, 'note' => 'Spent.']);

    return [$referrer->refresh(), $referred, $order, $product];
}

test('DISC-11: cancelling after the referrer spent the reward reverses it in full and the balance goes negative', function () {
    [$referrer, , $order] = disc11SpentReward();
    expect($referrer->points_balance)->toBe(20);

    $order->update(['status' => OrderStatus::CANCELLED]);

    expect($referrer->refresh()->points_balance)->toBe(-30);
    $revokes = ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REVOKE)->get();
    expect($revokes)->toHaveCount(1)
        ->and($revokes->first()->points)->toBe(-50)
        ->and((int) ReferralPointTransaction::where('user_id', $referrer->id)->sum('points'))->toBe(-30);
});

test('DISC-11: repeated cancel/refund processing never reverses the same reward twice', function () {
    [$referrer, $referred, $order] = disc11SpentReward();

    $order->update(['status' => OrderStatus::CANCELLED]);
    $order->update(['status' => OrderStatus::REFUNDED]);
    app(ReferralService::class)->handleOrderCancelledOrRefunded($order->fresh());
    app(ReferralService::class)->handleOrderCancelledOrRefunded(Order::find($order->id));

    expect($referrer->refresh()->points_balance)->toBe(-30)
        ->and(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REVOKE)->count())->toBe(1);
});

test('DISC-11: newly earned points offset a negative balance first', function () {
    [$referrer, , $order] = disc11SpentReward();
    $order->update(['status' => OrderStatus::CANCELLED]);
    Setting::updateOrCreate(['key' => 'daily_login_base_points'], ['value' => 10, 'group' => 'referral']);

    $result = app(LoyaltyPointsService::class)->claimDailyLogin($referrer);

    expect($result['awarded'])->toBeTrue()
        ->and($referrer->refresh()->points_balance)->toBe(-20);
});

test('DISC-11: checkout offers zero points and cannot spend a zero or negative balance', function (int $balance) {
    enableReferralProgram();
    $customer = User::factory()->create(['points_balance' => $balance]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $customer->id)->delete();

    $this->actingAs($customer)
        ->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pointsBalance', 0));

    $this->actingAs($customer)->post(route('checkout.store'), codPayload([
        'email' => $customer->email,
        'redeem_points' => true,
        'points_to_redeem' => 50,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    $order = Order::where('user_id', $customer->id)->firstOrFail();
    expect($order->points_redeemed)->toBeNull()
        ->and((float) $order->discount)->toBe(0.0)
        ->and($customer->refresh()->points_balance)->toBe($balance)
        ->and(ReferralPointTransaction::where('user_id', $customer->id)->where('type', PointTransactionType::REDEEM)->exists())->toBeFalse();
})->with(['zero balance' => 0, 'negative balance' => -30]);

/*
 * DISC-11 wording (owner, 2026-11-13): a negative balance can come from a
 * reversed referral reward or reversed order points, so the note is generic.
 * Source-level check: this project has no JavaScript test runner.
 */
test('DISC-11: the Rewards page note covers any reversal, shows only below zero, and the checkout value is never negative', function () {
    $source = (string) file_get_contents(resource_path('js/pages/account/Referral.tsx'));
    $text = (string) preg_replace('/\s+/', ' ', $source);

    expect($text)->toContain('Your balance is below zero because points were reversed after an order was cancelled or refunded. New points you earn will bring it back up first.')
        ->and($text)->not->toContain('because a referral reward was reversed after the order was cancelled')
        ->and($text)->toContain('{pointsBalance < 0 && (')
        ->and($text)->toContain('Math.max(0, pointsBalance) * pointsValue');
});

// ── DISC-36: the settings page shows the values actually in effect ──────────

test('DISC-36: with nothing saved, the referral settings page shows the defaults the app uses, not 0', function () {
    actingAs(createMotherAdmin(), 'admin');
    $referrals = app(ReferralService::class);
    $loyalty = app(LoyaltyPointsService::class);

    $this->get(route('admin.settings.referral'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.referral_enabled', $referrals->isEnabled())
            ->where('settings.referral_min_order_amount', fn ($value) => (float) $value === $referrals->minOrderAmountForDiscount())
            ->where('settings.referral_reward_points', $referrals->rewardPoints())
            ->where('settings.referral_reward_delay_days', $referrals->rewardDelayDays())
            ->where('settings.referral_unlock_order_count', $referrals->unlockOrderCount())
            ->where('settings.referral_reward_cap_amount', fn ($value) => (float) $value === $referrals->rewardCapAmount())
            ->where('settings.referral_reward_cap_period', $referrals->rewardCapPeriod())
            ->where('settings.points_redeem_value_bdt', fn ($value) => (float) $value === $referrals->pointsValue())
            ->where('settings.loyalty_earn_rate_bdt_per_point', fn ($value) => (float) $value === $loyalty->earnRateBdtPerPoint())
            ->where('settings.loyalty_earn_delay_days', $loyalty->earnDelayDays())
            ->where('settings.daily_login_base_points', $loyalty->dailyLoginBasePoints())
            ->where('settings.daily_login_cap_days', $loyalty->dailyLoginCapDays())
            ->where('settings.points_redeem_max_percent', fn ($value) => (float) $value === $loyalty->redeemMaxPercent())
            ->where('settings.registration_bonus_points', 20)
            ->where('settings.review_bonus_points', 15)
            ->where('settings.referral_discount_type', 'percent')
            ->where('settings.referral_discount_value', 0)
        );

    // And they are the real, non-zero defaults.
    expect($referrals->rewardPoints())->toBe(100)
        ->and($loyalty->earnRateBdtPerPoint())->toBe(150.0)
        ->and($loyalty->redeemMaxPercent())->toBe(50.0);
});

test('DISC-36: a saved value, including a deliberate 0, still wins over the default', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'referral_reward_points'], ['value' => 0, 'group' => 'referral']);
    Setting::updateOrCreate(['key' => 'points_redeem_max_percent'], ['value' => 25, 'group' => 'referral']);

    $this->get(route('admin.settings.referral'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.referral_reward_points', 0)
            ->where('settings.points_redeem_max_percent', 25)
            ->where('settings.review_bonus_points', 15));
});

test('DISC-36: a setting saved as null shows the default, as the app treats it', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'daily_login_cap_days'], ['value' => null, 'group' => 'referral']);

    $this->get(route('admin.settings.referral'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('settings.daily_login_cap_days', app(LoyaltyPointsService::class)->dailyLoginCapDays()));
});

test('DISC-36: the reward cap hint explains that 0 means no limit', function () {
    expect((string) file_get_contents(resource_path('js/pages/admin/settings/referral.tsx')))->toContain('0 = no limit.');
});
