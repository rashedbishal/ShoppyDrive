<?php

declare(strict_types=1);

/*
 * SEC-012: the anonymous coupon check returned the whole coupon record
 * (usage count, limits, dates) and different messages for disabled, future
 * and expired codes. It now returns only what the pages need, and one
 * message for every "not available" state.
 */

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('a valid code returns only the fields the cart and checkout need', function () {
    Coupon::factory()->create(['code' => 'EID500', 'type' => 'fixed', 'value' => 50, 'used_count' => 312, 'max_uses' => 500, 'per_user_limit' => 1, 'expires_at' => now()->addDays(5)]);

    $coupon = postJson(route('coupon.validate'), ['code' => 'EID500', 'subtotal' => 1000])->assertOk()->json('coupon');

    expect(array_keys($coupon))->toEqualCanonicalizing(['id', 'code', 'type', 'value'])
        ->and(json_encode($coupon))->not->toContain('312');
});

test('a selected-items coupon adds only eligible_keys', function () {
    $product = Product::factory()->create(['price' => 100]);
    $coupon = Coupon::factory()->create(['code' => 'PICK', 'type' => 'fixed', 'value' => 10, 'applies_to' => 'selected', 'used_count' => 7]);
    $coupon->productTargets()->create(['product_id' => $product->id, 'variant_id' => null]);

    $body = postJson(route('coupon.validate'), [
        'code' => 'PICK', 'subtotal' => 100, 'items' => [['product_id' => $product->id, 'variant_id' => null, 'subtotal' => 100]],
    ])->assertOk()->json('coupon');

    expect(array_keys($body))->toEqualCanonicalizing(['id', 'code', 'type', 'value', 'eligible_keys'])
        ->and($body['eligible_keys'])->toBe([$product->id.':']);
});

test('disabled, future, expired and used-up coupons all answer the same', function (array $state) {
    Coupon::factory()->create(['code' => 'HIDDEN', 'is_active' => true, 'starts_at' => null, 'expires_at' => null, 'max_uses' => null, ...$state]);

    postJson(route('coupon.validate'), ['code' => 'HIDDEN', 'subtotal' => 1000])
        ->assertUnprocessable()
        ->assertExactJson(['error' => "This coupon isn't available."]);
})->with([
    'disabled' => fn () => ['is_active' => false],
    'not started' => fn () => ['starts_at' => now()->addDay()],
    'expired' => fn () => ['expires_at' => now()->subDay()],
    'used up' => fn () => ['max_uses' => 5, 'used_count' => 5],
]);

test('the minimum order message still helps real shoppers', function () {
    Coupon::factory()->create(['code' => 'MIN', 'min_order_amount' => 500, 'starts_at' => null, 'expires_at' => null, 'max_uses' => null, 'is_active' => true]);

    postJson(route('coupon.validate'), ['code' => 'MIN', 'subtotal' => 100])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'Minimum order amount of ৳500 required.');
});
