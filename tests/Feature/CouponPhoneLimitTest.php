<?php

declare(strict_types=1);

/*
 * BIZ-015: per-customer coupon limits counted account uses and guest uses
 * separately, so a limit-1 coupon could be used once logged in and again
 * as a guest (or from a second account) with the same phone. Uses with the
 * same phone now count together. A different phone can't be tied to the
 * same person without verifying it, so it still works.
 */

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $this->coupon = Coupon::factory()->create(['code' => 'EID500', 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 1, 'max_uses' => null]);
});

function useEid500(?User $user, string $phone)
{
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50]);

    if ($user !== null) {
        Cart::query()->where('user_id', $user->id)->delete();
        test()->actingAs($user);
    } else {
        auth()->logout();
    }

    return test()->post(route('checkout.store'), [
        'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => $phone, 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(), 'coupon_code' => 'EID500',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]],
    ]);
}

test('used while logged in, the same phone cannot use it again as a guest', function () {
    useEid500(User::factory()->create(), '01711111111')->assertSessionHasNoErrors();

    useEid500(null, '01711111111')->assertSessionHasErrors('coupon_code');

    expect($this->coupon->refresh()->used_count)->toBe(1);
});

test('used as a guest, the same phone cannot use it again from an account', function () {
    useEid500(null, '01711111111')->assertSessionHasNoErrors();

    useEid500(User::factory()->create(), '01711111111')->assertSessionHasErrors('coupon_code');

    expect($this->coupon->refresh()->used_count)->toBe(1);
});

test('two accounts with the same phone share the limit', function () {
    useEid500(User::factory()->create(), '01711111111')->assertSessionHasNoErrors();

    useEid500(User::factory()->create(), '01711111111')->assertSessionHasErrors('coupon_code');
});

test('an account is still limited even with a different phone', function () {
    $user = User::factory()->create();
    useEid500($user, '01711111111')->assertSessionHasNoErrors();

    useEid500($user, '01822222222')->assertSessionHasErrors('coupon_code');
});

test('a genuinely different customer with a different phone can still use it', function () {
    useEid500(User::factory()->create(), '01711111111')->assertSessionHasNoErrors();
    useEid500(null, '01933333333')->assertSessionHasNoErrors();

    expect($this->coupon->refresh()->used_count)->toBe(2);
});

test('a cancelled order frees the use again for that phone', function () {
    useEid500(User::factory()->create(), '01711111111')->assertSessionHasNoErrors();
    app(CouponService::class)->release(Order::sole());

    useEid500(null, '01711111111')->assertSessionHasNoErrors();
});
