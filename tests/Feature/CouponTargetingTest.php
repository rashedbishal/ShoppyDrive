<?php

declare(strict_types=1);

use App\Enums\CouponAppliesTo;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReceivedSms;
use App\Models\SubCategory;
use App\Models\User;
use App\Services\RefundCalculator;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
    $this->otherCategory = Category::factory()->create();
    $this->otherSubCategory = SubCategory::factory()->create(['category_id' => $this->otherCategory->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function targetedCouponPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'TARGET20',
        'name' => 'Targeted',
        'type' => 'percent',
        'value' => 20,
        'is_active' => true,
        'applies_to' => 'selected',
    ], $overrides);
}

function shelfProduct(float $price = 1000): Product
{
    return Product::factory()->create([
        'price' => $price,
        'compare_price' => null,
        'stock_quantity' => 50,
        'category_id' => test()->otherCategory->id,
        'sub_category_id' => test()->otherSubCategory->id,
    ]);
}

function selectedCoupon(array $attributes = []): Coupon
{
    return Coupon::factory()->create(array_merge([
        'code' => 'TARGET20',
        'type' => 'percent',
        'value' => 20,
        'applies_to' => CouponAppliesTo::SELECTED,
    ], $attributes));
}

/**
 * @param  array<int, array{0: Product, 1?: ProductVariant|null}>  $lines
 */
function checkoutWithCoupon(User $user, array $lines, string $code = 'TARGET20', string $method = 'cod')
{
    Cart::query()->where('user_id', $user->id)->delete();

    return test()->actingAs($user)->post(route('checkout.store'), [
        // BIZ-015: each test customer has their own phone; the same phone
        // would count as the same person for per-customer coupon limits.
        'name' => 'Buyer', 'email' => $user->email, 'phone' => sprintf('017%08d', $user->id),
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => $method,
        'order_token' => (string) Str::uuid(),
        'coupon_code' => $code,
        'items' => array_map(fn (array $line): array => [
            'product_id' => $line[0]->id,
            'variant_id' => ($line[1] ?? null)?->id,
            'quantity' => 1,
        ], $lines),
    ]);
}

// ── Admin: saving ────────────────────────────────────────────────────────────

test('a coupon can mix categories, subcategories, products and variants', function () {
    actingAs(createAdmin(), 'admin');
    $whole = shelfProduct();
    $variantProduct = shelfProduct();
    $variant = ProductVariant::factory()->create(['product_id' => $variantProduct->id]);

    post(route('admin.coupons.store'), targetedCouponPayload([
        'target_category_ids' => [$this->category->id],
        'target_sub_category_ids' => [$this->otherSubCategory->id],
        'target_products' => [
            ['product_id' => $whole->id, 'variant_id' => null],
            ['product_id' => $variantProduct->id, 'variant_id' => $variant->id],
        ],
    ]))->assertSessionHasNoErrors();

    $coupon = Coupon::where('code', 'TARGET20')->firstOrFail();

    expect($coupon->applies_to)->toBe(CouponAppliesTo::SELECTED)
        ->and($coupon->categories()->pluck('categories.id')->all())->toBe([$this->category->id])
        ->and($coupon->subCategories()->pluck('sub_categories.id')->all())->toBe([$this->otherSubCategory->id])
        ->and($coupon->productTargets()->count())->toBe(2);
});

test('a coupon defaults to all products, as existing coupons do', function () {
    actingAs(createAdmin(), 'admin');

    post(route('admin.coupons.store'), targetedCouponPayload(['applies_to' => 'all', 'target_category_ids' => [$this->category->id]]))
        ->assertSessionHasNoErrors();

    $coupon = Coupon::where('code', 'TARGET20')->firstOrFail();

    expect($coupon->fresh()->applies_to)->toBe(CouponAppliesTo::ALL)
        ->and($coupon->categories()->count())->toBe(0);
});

test('invalid selections are refused and nothing is saved', function (Closure $targets, string $errorKey) {
    actingAs(createAdmin(), 'admin');

    post(route('admin.coupons.store'), targetedCouponPayload($targets->call($this)))
        ->assertSessionHasErrors($errorKey);

    expect(Coupon::where('code', 'TARGET20')->exists())->toBeFalse();
})->with([
    'nothing selected' => [fn () => [], 'applies_to'],
    'same category twice' => [fn () => ['target_category_ids' => [$this->category->id, $this->category->id]], 'target_category_ids.0'],
    'same subcategory twice' => [fn () => ['target_sub_category_ids' => [$this->subCategory->id, $this->subCategory->id]], 'target_sub_category_ids.0'],
    'subcategory of a selected category' => [fn () => ['target_category_ids' => [$this->category->id], 'target_sub_category_ids' => [$this->subCategory->id]], 'target_sub_category_ids'],
    'same product twice' => [function () {
        $product = shelfProduct();

        return ['target_products' => [['product_id' => $product->id], ['product_id' => $product->id, 'variant_id' => null]]];
    }, 'target_products.1.product_id'],
    'same variant twice' => [function () {
        $product = shelfProduct();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        return ['target_products' => [['product_id' => $product->id, 'variant_id' => $variant->id], ['product_id' => $product->id, 'variant_id' => $variant->id]]];
    }, 'target_products.1.product_id'],
    'variant of a listed whole product' => [function () {
        $product = shelfProduct();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        return ['target_products' => [['product_id' => $product->id], ['product_id' => $product->id, 'variant_id' => $variant->id]]];
    }, 'target_products.1.variant_id'],
    'another product\'s variant' => [function () {
        $variant = ProductVariant::factory()->create(['product_id' => shelfProduct()->id]);

        return ['target_products' => [['product_id' => shelfProduct()->id, 'variant_id' => $variant->id]]];
    }, 'target_products.0.variant_id'],
]);

test('the edit form shows the selection, and editing replaces it and records it', function () {
    actingAs(createAdmin(), 'admin');
    $product = shelfProduct();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['Size' => 'M']]);
    $coupon = selectedCoupon();
    $coupon->categories()->attach($this->category->id);
    $coupon->productTargets()->create(['product_id' => $product->id, 'variant_id' => $variant->id]);

    $this->get(route('admin.coupons.edit', $coupon))->assertInertia(fn (Assert $page) => $page
        ->where('coupon.applies_to', 'selected')
        ->where('coupon.target_category_ids', [$this->category->id])
        ->where('coupon.target_products.0.variant_id', $variant->id)
        ->where('coupon.target_products.0.variant_label', 'Size: M')
        ->where('coupon.target_products.0.product_name', $product->name));

    put(route('admin.coupons.update', $coupon), targetedCouponPayload(['target_sub_category_ids' => [$this->subCategory->id]]))
        ->assertSessionHasNoErrors();

    expect($coupon->categories()->count())->toBe(0)
        ->and($coupon->productTargets()->count())->toBe(0)
        ->and($coupon->subCategories()->count())->toBe(1)
        ->and(Activity::where('event', 'targets_updated')->where('subject_id', $coupon->id)->exists())->toBeTrue();
});

test('switching a coupon back to all products clears its selection', function () {
    actingAs(createAdmin(), 'admin');
    $coupon = selectedCoupon();
    $coupon->categories()->attach($this->category->id);

    put(route('admin.coupons.update', $coupon), targetedCouponPayload(['applies_to' => 'all']))->assertSessionHasNoErrors();

    expect($coupon->fresh()->applies_to)->toBe(CouponAppliesTo::ALL)
        ->and($coupon->categories()->count())->toBe(0);
});

test('product search lists variants and needs coupon access', function () {
    $product = shelfProduct();
    ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['Size' => 'M']]);

    actingAs(createAdmin(), 'admin');
    $this->getJson(route('admin.coupons.products.search', ['q' => $product->name]))
        ->assertOk()
        ->assertJsonPath('0.id', $product->id)
        ->assertJsonPath('0.variants.0.label', 'Size: M');

    auth('admin')->logout();
    actingAs(createAdmin(['is_mother' => false, 'email' => 'staff@example.test', 'permissions' => []]), 'admin');
    $this->getJson(route('admin.coupons.products.search', ['q' => $product->name]))->assertForbidden();
});

// ── Checkout: what gets discounted ───────────────────────────────────────────

test('only listed items are discounted, and the order records which were', function () {
    $listed = shelfProduct(1000);
    $other = shelfProduct(500);
    selectedCoupon()->productTargets()->create(['product_id' => $listed->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$listed], [$other]])->assertSessionHasNoErrors();

    $order = Order::with('items')->where('user_id', $user->id)->sole();

    expect((float) $order->discount)->toBe(200.0)
        ->and((float) $order->total)->toBe((float) $order->subtotal + (float) $order->shipping_cost - 200)
        ->and($order->items->firstWhere('product_id', $listed->id)->coupon_applied)->toBeTrue()
        ->and($order->items->firstWhere('product_id', $other->id)->coupon_applied)->toBeFalse();
});

test('a fixed amount is capped at what the coupon covers, not the whole cart', function () {
    $listed = shelfProduct(300);
    $other = shelfProduct(5000);
    selectedCoupon(['type' => 'fixed', 'value' => 500])->productTargets()->create(['product_id' => $listed->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$listed], [$other]])->assertSessionHasNoErrors();

    expect((float) Order::where('user_id', $user->id)->sole()->discount)->toBe(300.0);
});

test('a category and a subcategory cover their products, and overlap discounts once', function () {
    $inCategory = Product::factory()->create(['price' => 1000, 'compare_price' => null, 'stock_quantity' => 50, 'category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id]);
    $inSubCategory = shelfProduct(500);
    $outside = Product::factory()->create(['price' => 400, 'compare_price' => null, 'stock_quantity' => 50]);
    $coupon = selectedCoupon();
    $coupon->categories()->attach($this->category->id);
    $coupon->subCategories()->attach($this->otherSubCategory->id);
    $coupon->productTargets()->create(['product_id' => $inCategory->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$inCategory], [$inSubCategory], [$outside]])->assertSessionHasNoErrors();

    expect((float) Order::where('user_id', $user->id)->sole()->discount)->toBe(300.0);
});

test('a variant target covers only that variant, and a whole-product target covers every variant', function () {
    $product = shelfProduct();
    $wanted = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);
    $unwanted = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 2000, 'stock_quantity' => 10]);
    $coupon = selectedCoupon();
    $target = $coupon->productTargets()->create(['product_id' => $product->id, 'variant_id' => $wanted->id]);

    $buyer = User::factory()->create();
    checkoutWithCoupon($buyer, [[$product, $wanted], [$product, $unwanted]])->assertSessionHasNoErrors();
    expect((float) Order::where('user_id', $buyer->id)->sole()->discount)->toBe(200.0);

    $unlisted = User::factory()->create();
    checkoutWithCoupon($unlisted, [[$product, $unwanted]])->assertSessionHasErrors('coupon_code');

    $target->update(['variant_id' => null]);
    $wholeBuyer = User::factory()->create();
    checkoutWithCoupon($wholeBuyer, [[$product, $unwanted]])->assertSessionHasNoErrors();
    expect((float) Order::where('user_id', $wholeBuyer->id)->sole()->discount)->toBe(400.0);
});

test('a cart with nothing covered is refused and uses nothing up', function () {
    selectedCoupon()->productTargets()->create(['product_id' => shelfProduct()->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[shelfProduct()]])->assertSessionHasErrors(['coupon_code' => Coupon::NOT_APPLICABLE_MESSAGE]);

    expect(Order::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Coupon::where('code', 'TARGET20')->sole()->used_count)->toBe(0);
});

test('the minimum order counts only covered items', function () {
    $listed = shelfProduct(200);
    selectedCoupon(['min_order_amount' => 1000])->productTargets()->create(['product_id' => $listed->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$listed], [shelfProduct(5000)]])->assertSessionHasErrors('coupon_code');

    expect(Order::where('user_id', $user->id)->exists())->toBeFalse();
});

test('an all-products coupon still discounts the whole cart and marks no lines', function () {
    Coupon::factory()->create(['code' => 'ALL10', 'type' => 'percent', 'value' => 10]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[shelfProduct(1000)], [shelfProduct(500)]], 'ALL10')->assertSessionHasNoErrors();

    $order = Order::with('items')->where('user_id', $user->id)->sole();

    expect((float) $order->discount)->toBe(150.0)
        ->and($order->items->pluck('coupon_applied')->unique()->all())->toBe([null]);
});

test('a mobile-banking order keeps the discount and which lines it covered', function () {
    $listed = shelfProduct(1000);
    $other = shelfProduct(500);
    selectedCoupon()->productTargets()->create(['product_id' => $listed->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$listed], [$other]], method: 'mobile_banking')->assertRedirect();

    $pending = PendingCheckout::where('user_id', $user->id)->sole();
    $transactionId = 'CPN'.Str::upper(Str::random(6));
    ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "TrxID {$transactionId}",
        'received_at' => now(), 'parsed_transaction_id' => $transactionId,
        'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => $transactionId])
        ->assertRedirect();

    $order = Order::with('items')->where('user_id', $user->id)->sole();

    expect((float) $order->discount)->toBe(200.0)
        ->and($order->items->firstWhere('product_id', $listed->id)->coupon_applied)->toBeTrue()
        ->and($order->items->firstWhere('product_id', $other->id)->coupon_applied)->toBeFalse();
});

// ── Preview ──────────────────────────────────────────────────────────────────

test('the preview reports which lines are covered, and refuses a cart with none', function () {
    $listed = shelfProduct();
    $other = shelfProduct();
    selectedCoupon()->productTargets()->create(['product_id' => $listed->id]);
    $line = fn (Product $product): array => ['product_id' => $product->id, 'variant_id' => null, 'subtotal' => 1000];

    $this->postJson(route('coupon.validate'), ['code' => 'TARGET20', 'subtotal' => 2000, 'items' => [$line($listed), $line($other)]])
        ->assertOk()
        ->assertJsonPath('coupon.code', 'TARGET20')
        ->assertJsonPath('coupon.eligible_keys', ["{$listed->id}:"]);

    $this->postJson(route('coupon.validate'), ['code' => 'TARGET20', 'subtotal' => 1000, 'items' => [$line($other)]])
        ->assertStatus(422)
        ->assertJsonPath('error', Coupon::NOT_APPLICABLE_MESSAGE);
});

test('the preview of an all-products coupon needs no lines and marks none', function () {
    Coupon::factory()->create(['code' => 'ALL10']);

    $this->postJson(route('coupon.validate'), ['code' => 'ALL10', 'subtotal' => 1000])
        ->assertOk()
        ->assertJsonMissingPath('coupon.eligible_keys');
});

// ── Refunds ──────────────────────────────────────────────────────────────────

test('refund values count a selected-items discount only against covered lines', function () {
    $listed = shelfProduct(1000);
    $other = shelfProduct(500);
    selectedCoupon()->productTargets()->create(['product_id' => $listed->id]);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$listed], [$other]])->assertSessionHasNoErrors();

    $order = Order::with('items')->where('user_id', $user->id)->sole();
    $net = app(RefundCalculator::class)->lineNetPaisa($order);

    expect($net[$order->items->firstWhere('product_id', $listed->id)->id])->toBe(80000)
        ->and($net[$order->items->firstWhere('product_id', $other->id)->id])->toBe(50000);
});

test('refund values still share an all-products discount across every line', function () {
    Coupon::factory()->create(['code' => 'ALL10', 'type' => 'percent', 'value' => 10]);
    $first = shelfProduct(1000);
    $second = shelfProduct(500);
    $user = User::factory()->create();

    checkoutWithCoupon($user, [[$first], [$second]], 'ALL10')->assertSessionHasNoErrors();

    $order = Order::with('items')->where('user_id', $user->id)->sole();
    $net = app(RefundCalculator::class)->lineNetPaisa($order);

    expect($net[$order->items->firstWhere('product_id', $first->id)->id])->toBe(90000)
        ->and($net[$order->items->firstWhere('product_id', $second->id)->id])->toBe(45000);
});
