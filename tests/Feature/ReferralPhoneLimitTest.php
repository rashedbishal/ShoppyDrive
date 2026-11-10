<?php

declare(strict_types=1);

/*
 * BIZ-024: the referred customer's one-time discount was tied only to the
 * account, so the same person could claim it again with each new email.
 * It is now refused once another account already has a referral discount
 * on record with the same phone OR the same normalized delivery address
 * (owner's lenient rule, extended to address since a phone alone was still
 * farmable with a new SIM while reusing the same household). The customer
 * is never charged more than the page showed: the first submit is refused
 * with an explanation, and the re-rendered page no longer shows it.
 * The sign-up loyalty bonus now waits for a verified email.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    foreach ([
        'referral_enabled' => true, 'referral_discount_type' => 'percent', 'referral_discount_value' => 10,
        'referral_min_order_amount' => 0, 'registration_bonus_points' => 20,
    ] as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
    }
    $this->referrer = User::factory()->create();
    $this->product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 50]);
});

function referredCheckout(User $user, string $phone, string $addressLine1 = 'House 1')
{
    Cart::query()->where('user_id', $user->id)->delete();

    return test()->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Rahim', 'email' => $user->email, 'phone' => $phone, 'address_line1' => $addressLine1, 'city' => 'Dhaka',
        'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => test()->product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 1000]],
    ]);
}

test('a second account with the same phone is refused once, told why, then orders without the discount', function () {
    $first = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($first, '01711111111')->assertSessionHasNoErrors();
    expect((float) Order::where('user_id', $first->id)->sole()->discount)->toBe(100.0);

    $second = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($second, '01711111111')
        ->assertSessionHasErrors('referral')
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'already used with this phone number'));
    expect(Order::where('user_id', $second->id)->exists())->toBeFalse();

    // The re-rendered checkout no longer shows the discount...
    $this->actingAs($second)->get(route('checkout.index'))
        ->assertInertia(fn (Assert $page) => $page->where('referralDiscountEligible', false)->where('referralDiscountPreview', 0));

    // ...and the next submit goes through at the full price.
    referredCheckout($second, '01711111111')->assertSessionHasNoErrors();
    $order = Order::where('user_id', $second->id)->sole();
    expect((float) $order->discount)->toBe(0.0)
        ->and($order->used_referral_discount)->toBeFalse()
        ->and($second->refresh()->referral_discount_used_order_id)->toBeNull();
});

test('a different phone and address still gets its own referral discount', function () {
    referredCheckout(User::factory()->create(['referred_by_id' => $this->referrer->id]), '01711111111', 'House 1')->assertSessionHasNoErrors();
    $other = User::factory()->create(['referred_by_id' => $this->referrer->id]);

    referredCheckout($other, '01822222222', 'House 2')->assertSessionHasNoErrors();

    expect((float) Order::where('user_id', $other->id)->sole()->discount)->toBe(100.0);
});

test('a phone whose earlier orders had no referral discount still gets one (lenient rule)', function () {
    referredCheckout(User::factory()->create(), '01711111111')->assertSessionHasNoErrors();
    $referred = User::factory()->create(['referred_by_id' => $this->referrer->id]);

    referredCheckout($referred, '01711111111')->assertSessionHasNoErrors();

    expect((float) Order::where('user_id', $referred->id)->sole()->discount)->toBe(100.0);
});

test('a cancelled referral order gives the discount back, so the phone no longer counts', function () {
    $first = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($first, '01711111111')->assertSessionHasNoErrors();
    Order::where('user_id', $first->id)->sole()->update(['status' => 'cancelled']);

    expect(app(ReferralService::class)->referralDiscountUsedByPhone('01711111111', User::factory()->create()->id))->toBeFalse();
});

test('a second account with a different phone but the same delivery address is refused once, told why, then orders without the discount', function () {
    $first = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($first, '01711111111', 'House 7, Road 3')->assertSessionHasNoErrors();
    expect((float) Order::where('user_id', $first->id)->sole()->discount)->toBe(100.0);

    $second = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($second, '01822222222', 'House 7, Road 3')
        ->assertSessionHasErrors('referral')
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'already used with this delivery address'));
    expect(Order::where('user_id', $second->id)->exists())->toBeFalse();

    // ...and the next submit (same phone/address) goes through at the full price.
    referredCheckout($second, '01822222222', 'House 7, Road 3')->assertSessionHasNoErrors();
    $order = Order::where('user_id', $second->id)->sole();
    expect((float) $order->discount)->toBe(0.0)
        ->and($order->used_referral_discount)->toBeFalse();
});

test('the address match ignores whitespace, case and punctuation, but never matches an incomplete address', function () {
    $service = app(ReferralService::class);
    $first = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($first, '01711111111', 'House 7, Road-3')->assertSessionHasNoErrors();
    $excludeId = User::factory()->create()->id;

    expect($service->referralDiscountUsedByAddress([
        'address_line1' => '  house   7  ROAD 3 ', 'city' => 'DHAKA', 'state' => 'dhaka',
    ], $excludeId))->toBeTrue();

    expect($service->referralDiscountUsedByAddress([
        'address_line1' => 'House 99, somewhere else', 'city' => 'Dhaka', 'state' => 'Dhaka',
    ], $excludeId))->toBeFalse();

    expect($service->referralDiscountUsedByAddress([
        'address_line1' => 'House 7, Road-3', 'city' => '', 'state' => 'Dhaka',
    ], $excludeId))->toBeFalse();

    expect($service->referralDiscountUsedByAddress(null, $excludeId))->toBeFalse();
});

test('a cancelled referral order gives the discount back, so the address no longer counts', function () {
    $first = User::factory()->create(['referred_by_id' => $this->referrer->id]);
    referredCheckout($first, '01711111111', 'House 7, Road 3')->assertSessionHasNoErrors();
    Order::where('user_id', $first->id)->sole()->update(['status' => 'cancelled']);

    $address = ['address_line1' => 'House 7, Road 3', 'city' => 'Dhaka', 'state' => 'Dhaka'];
    expect(app(ReferralService::class)->referralDiscountUsedByAddress($address, User::factory()->create()->id))->toBeFalse();
});

test('a Facebook sign-up gets the sign-up bonus only after verifying, a Google-verified one immediately', function () {
    Socialite::fake('facebook', (new SocialiteUser)->map(['id' => 'fb-1', 'name' => 'Fb User', 'email' => 'fb@example.com']));
    $this->get('/auth/facebook/callback');
    expect(User::where('email', 'fb@example.com')->sole()->points_balance)->toBe(0);
    auth()->logout();

    Socialite::fake('google', (new SocialiteUser)->setRaw(['email_verified' => true])->map(['id' => 'g-1', 'name' => 'G User', 'email' => 'g@example.com']));
    $this->get('/auth/google/callback');
    expect(User::where('email', 'g@example.com')->sole()->points_balance)->toBe(20);
});
