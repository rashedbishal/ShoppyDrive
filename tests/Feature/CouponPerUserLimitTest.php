<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function limitCouponPayload(array $overrides = []): array
{
    return array_merge(['code' => 'LIMIT', 'name' => 'Limit', 'type' => 'fixed', 'value' => 10, 'is_active' => true], $overrides);
}

function orderWithLimitCoupon(?User $user, string $phone = '01700000000')
{
    $product = Product::factory()->create(['price' => 100, 'compare_price' => null, 'stock_quantity' => 50]);

    if ($user !== null) {
        Cart::query()->where('user_id', $user->id)->delete();
        test()->actingAs($user);
    }

    return test()->post(route('checkout.store'), [
        'name' => 'Buyer', 'email' => 'buyer@example.com', 'phone' => $phone,
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'coupon_code' => 'LIMIT',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]);
}

test('an empty limit saves as no limit, as the form submits it', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.coupons.store'), limitCouponPayload(['per_user_limit' => '']))->assertSessionHasNoErrors();

    expect(Coupon::where('code', 'LIMIT')->sole()->per_user_limit)->toBeNull();
});

test('a limit can be set, and cleared again by editing', function () {
    actingAs(createAdmin(), 'admin');
    post(route('admin.coupons.store'), limitCouponPayload(['per_user_limit' => '3']))->assertSessionHasNoErrors();
    $coupon = Coupon::where('code', 'LIMIT')->sole();

    expect($coupon->per_user_limit)->toBe(3);

    put(route('admin.coupons.update', $coupon), limitCouponPayload(['per_user_limit' => '']))->assertSessionHasNoErrors();

    expect($coupon->fresh()->per_user_limit)->toBeNull();
});

test('a limit below one is refused', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.coupons.store'), limitCouponPayload(['per_user_limit' => '0']))->assertSessionHasErrors('per_user_limit');
});

test('a coupon created without mentioning a limit keeps the default of one', function () {
    $coupon = Coupon::create(limitCouponPayload());

    expect($coupon->fresh()->per_user_limit)->toBe(1);
});

test('a customer can reuse a coupon with no limit', function () {
    Coupon::factory()->create(limitCouponPayload(['per_user_limit' => null]));
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        orderWithLimitCoupon($user)->assertSessionHasNoErrors();
    }

    expect(Order::where('user_id', $user->id)->whereNotNull('coupon_id')->count())->toBe(3)
        ->and(Coupon::where('code', 'LIMIT')->sole()->used_count)->toBe(3);
});

test('a guest can reuse a coupon with no limit, in the preview and at checkout', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    Coupon::factory()->create(limitCouponPayload(['per_user_limit' => null]));

    foreach (range(1, 2) as $ignored) {
        orderWithLimitCoupon(null)->assertSessionHasNoErrors();
    }

    expect(Order::whereNull('user_id')->whereNotNull('coupon_id')->count())->toBe(2);

    $this->postJson(route('coupon.validate'), ['code' => 'LIMIT', 'subtotal' => 100, 'phone' => '01700000000'])->assertOk();
});

test('a limit of two allows exactly two uses per customer', function () {
    Coupon::factory()->create(limitCouponPayload(['per_user_limit' => 2]));
    $user = User::factory()->create();

    orderWithLimitCoupon($user)->assertSessionHasNoErrors();
    orderWithLimitCoupon($user)->assertSessionHasNoErrors();
    orderWithLimitCoupon($user)->assertSessionHasErrors('coupon_code');

    expect(Order::where('user_id', $user->id)->count())->toBe(2);

    // BIZ-015: another customer, with their own phone.
    orderWithLimitCoupon(User::factory()->create(), '01800000000')->assertSessionHasNoErrors();
});

test('max uses still caps a coupon with no per-customer limit', function () {
    Coupon::factory()->create(limitCouponPayload(['per_user_limit' => null, 'max_uses' => 1]));
    $user = User::factory()->create();

    orderWithLimitCoupon($user)->assertSessionHasNoErrors();
    orderWithLimitCoupon($user)->assertSessionHasErrors('coupon_code');
});
