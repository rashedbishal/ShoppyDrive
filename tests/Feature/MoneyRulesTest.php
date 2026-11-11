<?php

declare(strict_types=1);

use App\Http\Requests\Admin\CouponRequest;
use App\Http\Requests\Admin\ProductBundleRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReceivedSms;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\LoyaltyPointsService;
use App\Support\BangladeshDistricts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function moneyCheckoutPayload(array $overrides = []): array
{
    return [
        'name' => 'Test Buyer', 'email' => 'buyer@example.com', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking', 'order_token' => (string) Str::uuid(),
        ...$overrides,
    ];
}

function moneyStagePayment(User $user, Product $product, int $quantity): PendingCheckout
{
    test()->actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity, 'price' => (float) $product->price]],
    ]))->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->sole();
}

function moneyPayStaged(User $user, PendingCheckout $pending): void
{
    $transactionId = 'MONEY'.Str::upper(Str::random(5));
    ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "TrxID {$transactionId}", 'received_at' => now(),
        'parsed_transaction_id' => $transactionId, 'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    test()->actingAs($user)
        ->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => $transactionId])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));
}

function moneyProduct(array $attributes = []): Product
{
    return Product::factory()->create(['price' => 500, 'stock_quantity' => 50, 'is_active' => true, 'published_at' => now()->subDay(), ...$attributes]);
}

test('paying a mobile checkout keeps items added while the payment was pending (BIZ-019)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $paid = moneyProduct();
    $later = moneyProduct();
    $pending = moneyStagePayment($user, $paid, 1);

    $cart = Cart::findOrFail($pending->cart_id);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $later->id, 'quantity' => 2, 'price' => 500]);

    moneyPayStaged($user, $pending);

    expect(Cart::find($cart->id))->not->toBeNull()
        ->and($cart->items()->pluck('quantity', 'product_id')->all())->toBe([$later->id => 2]);
});

test('paying a mobile checkout only takes the paid quantity from a line that grew (BIZ-019)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $product = moneyProduct();
    $pending = moneyStagePayment($user, $product, 1);
    CartItem::where('cart_id', $pending->cart_id)->update(['quantity' => 3]);

    moneyPayStaged($user, $pending);

    expect(CartItem::where('cart_id', $pending->cart_id)->value('quantity'))->toBe(2);
});

test('paying a mobile checkout with nothing else in the cart still removes the cart (BIZ-019)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $pending = moneyStagePayment($user, moneyProduct(), 1);

    moneyPayStaged($user, $pending);

    expect(Cart::find($pending->cart_id))->toBeNull();
});

test('staging a mobile checkout never buys or loses an item added to the cart after the page was loaded (BIZ-019)', function () {
    // Checkout.tsx submits its own client-side cart snapshot, taken when
    // the page loaded — this simulates another tab adding a second
    // product to the same cart after that, before this stale submission
    // (which only knows about $paid) reaches the server.
    $user = User::factory()->create(['email_verified_at' => now()]);
    $paid = moneyProduct();
    $addedLater = moneyProduct();
    $cart = Cart::create(['user_id' => $user->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $paid->id, 'quantity' => 1, 'price' => 500]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $addedLater->id, 'quantity' => 2, 'price' => 500]);

    $pending = moneyStagePayment($user, $paid, 1);

    // Staging itself never removes anything from the cart — only the
    // staked snapshot that decides what the customer is told to pay for
    // must exclude $addedLater; the cart stays fully intact until payment
    // actually completes.
    expect(collect($pending->checkout_data['items'])->pluck('product_id')->all())->toBe([$paid->id])
        ->and((float) $pending->checkout_data['subtotal'])->toBe(500.0)
        ->and($cart->fresh()->items()->pluck('quantity', 'product_id')->all())->toBe([$paid->id => 1, $addedLater->id => 2]);

    moneyPayStaged($user, $pending);

    // Paying removes only what was staged; $addedLater survives.
    expect($cart->fresh())->not->toBeNull()
        ->and($cart->items()->pluck('quantity', 'product_id')->all())->toBe([$addedLater->id => 2]);
});

test('placing a COD order never buys or loses an item added to the cart after the page was loaded (BIZ-019)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ordered = moneyProduct();
    $addedLater = moneyProduct();
    $cart = Cart::create(['user_id' => $user->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $ordered->id, 'quantity' => 1, 'price' => 500]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $addedLater->id, 'quantity' => 2, 'price' => 500]);

    $response = actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email,
        'payment_method' => 'cod',
        'items' => [['product_id' => $ordered->id, 'variant_id' => null, 'quantity' => 1, 'price' => (float) $ordered->price]],
    ]));

    $order = Order::query()->where('user_id', $user->id)->sole();
    $response->assertRedirect(route('order.confirmation', $order->order_number));

    expect($order->items()->pluck('product_id')->all())->toBe([$ordered->id])
        ->and($cart->fresh())->not->toBeNull()
        ->and($cart->items()->pluck('quantity', 'product_id')->all())->toBe([$addedLater->id => 2]);
});

test('the order confirmation page hands the browser the cart that is left, so items added in another tab survive', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ordered = moneyProduct();
    $addedInOtherTab = moneyProduct();
    $cart = Cart::create(['user_id' => $user->id]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $ordered->id, 'quantity' => 1, 'price' => 500]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $addedInOtherTab->id, 'quantity' => 1, 'price' => 500]);

    actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email,
        'payment_method' => 'cod',
        'items' => [['product_id' => $ordered->id, 'variant_id' => null, 'quantity' => 1, 'price' => (float) $ordered->price]],
    ]));

    $order = Order::query()->where('user_id', $user->id)->sole();

    // app.tsx replaces the logged-in browser cart with this shared `cart`
    // prop on the confirmation page instead of emptying it.
    actingAs($user)->get(route('order.confirmation', $order->order_number))
        ->assertInertia(fn (Assert $page) => $page
            ->component('order/OrderConfirmation')
            ->where('keepCart', false)
            ->has('cart.items', 1)
            ->where('cart.items.0.product.id', $addedInOtherTab->id)
        );
});

test('the referral preview counts a Buy Now item when the cart is empty (BIZ-018)', function () {
    Setting::insert([
        ['key' => 'referral_enabled', 'value' => json_encode(true), 'group' => 'referral'],
        ['key' => 'referral_discount_type', 'value' => json_encode('percent'), 'group' => 'referral'],
        ['key' => 'referral_discount_value', 'value' => json_encode(10), 'group' => 'referral'],
    ]);
    $referrer = User::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now(), 'referred_by_id' => $referrer->id]);
    $product = moneyProduct(['price' => 1000]);

    actingAs($user)->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_qty' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('referralDiscountEligible', true)
            ->where('referralDiscountPreview', fn ($preview): bool => (float) $preview === 200.0));
});

test('a ৳0 total cannot choose bKash, since no SMS could ever confirm it (BIZ-020)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $product = moneyProduct(['price' => 500, 'free_delivery' => true]);
    Coupon::factory()->create(['code' => 'ALLFREE', 'type' => 'percent', 'value' => 100, 'is_active' => true]);

    actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email,
        'coupon_code' => 'ALLFREE',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 500]],
    ]))->assertRedirect()->assertSessionHasErrors('payment_method')->assertSessionHas('error');

    expect(PendingCheckout::query()->count())->toBe(0);
});

test('a coupon value must make sense for its type (BIZ-020)', function (string $type, float|int $value, bool $valid) {
    $rules = (new CouponRequest)->merge(['type' => $type, 'value' => $value])->rules();

    expect(Validator::make(['value' => $value], ['value' => $rules['value']])->passes())->toBe($valid);
})->with([
    'percent at the limit' => ['percent', 100, true],
    'percent over the limit' => ['percent', 110, false],
    'percent of nothing' => ['percent', 0, false],
    'a fixed amount can exceed 100' => ['fixed', 500, true],
    'a fixed amount of nothing' => ['fixed', 0, false],
]);

function moneyFlashSalePayload(array $item): array
{
    return [
        'name' => 'Sale', 'starts_at' => '2027-01-01T10:00', 'ends_at' => '2027-01-02T10:00', 'is_active' => true,
        'items' => [$item],
    ];
}

test('a flash price must be above ৳0 and below the regular price (BIZ-020)', function (float $salePrice, bool $valid) {
    $product = moneyProduct(['price' => 500]);
    actingAs(createAdmin(), 'admin');

    $response = $this->post(route('admin.flash-sales.store'), moneyFlashSalePayload(['product_id' => $product->id, 'sale_price' => $salePrice]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('items.0.sale_price');
})->with([
    'below' => [499.99, true],
    'equal' => [500, false],
    'above' => [900, false],
    'zero' => [0, false],
]);

test('a whole-product flash price must be below the cheapest variant (BIZ-020)', function () {
    $product = moneyProduct(['price' => 1000]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 800]);
    actingAs(createAdmin(), 'admin');

    $this->post(route('admin.flash-sales.store'), moneyFlashSalePayload(['product_id' => $product->id, 'sale_price' => 900]))
        ->assertSessionHasErrors('items.0.sale_price');
});

test('a flash price on one variant is compared with that variant (BIZ-020)', function () {
    $product = moneyProduct(['price' => 500]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
    actingAs(createAdmin(), 'admin');

    $this->post(route('admin.flash-sales.store'), moneyFlashSalePayload(['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 900]))
        ->assertSessionHasNoErrors();
});

test('an invented district is refused on checkout, while every real one passes (BIZ-021)', function (string $state, bool $valid) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $product = moneyProduct();

    $response = actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email, 'state' => $state, 'payment_method' => 'cod',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 500]],
    ]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('state');
    expect(Order::query()->exists())->toBe($valid);
})->with([
    'an apostrophe' => ["Cox's Bazar", true],
    'Dhaka' => ['Dhaka', true],
    'invented' => ['Atlantis', false],
    'a real district in the wrong case' => ['dhaka', false],
]);

test('an invented district cannot be saved in the address book, the way around checkout (BIZ-021)', function () {
    actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->post(route('account.addresses.store'), [
            'label' => 'Home', 'name' => 'Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1',
            'city' => 'Anywhere', 'state' => 'Atlantis', 'country' => 'Bangladesh',
        ])->assertSessionHasErrors('state');
});

test('the server and the checkout form list exactly the same districts (BIZ-021)', function () {
    $source = (string) file_get_contents(resource_path('js/lib/bangladesh-geography.ts'));
    $list = Str::before(Str::after($source, 'BANGLADESH_DISTRICTS = ['), '];');
    preg_match_all("/^\s*(?:'([^']+)'|\"([^\"]+)\"),$/m", $list, $matches);
    $formDistricts = array_values(array_filter(array_merge(...array_map(null, $matches[1], $matches[2]))));

    expect(BangladeshDistricts::ALL)->toHaveCount(64)->toBe($formDistricts);
});

function moneyReviewedProduct(User $user): array
{
    $product = moneyProduct();
    $order = Order::create([
        'order_number' => 'ORD-REV-'.uniqid(), 'user_id' => $user->id, 'status' => 'delivered', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 500,
        'shipping_address' => [], 'billing_address' => [],
    ]);

    return [$product, $order];
}

function moneyApprovedReview(User $user, Product $product, Order $order): Review
{
    return Review::create([
        'product_id' => $product->id, 'user_id' => $user->id, 'order_id' => $order->id, 'rating' => 5,
        'body' => 'A genuinely useful review.', 'is_approved' => true,
    ]);
}

test('rewriting a deleted review earns no second bonus (BIZ-036)', function () {
    Setting::create(['key' => 'review_bonus_points', 'value' => 20, 'group' => 'loyalty']);
    $user = User::factory()->create(['points_balance' => 0]);
    [$product, $order] = moneyReviewedProduct($user);
    $loyalty = app(LoyaltyPointsService::class);

    $first = moneyApprovedReview($user, $product, $order);
    $loyalty->grantReviewBonus($first);
    expect($user->fresh()->points_balance)->toBe(20);

    $first->delete();
    $loyalty->grantReviewBonus(moneyApprovedReview($user, $product, $order));

    expect($user->fresh()->points_balance)->toBe(20);
});

test('a review of a different product still earns its bonus (BIZ-036)', function () {
    Setting::create(['key' => 'review_bonus_points', 'value' => 20, 'group' => 'loyalty']);
    $user = User::factory()->create(['points_balance' => 0]);
    [$product, $order] = moneyReviewedProduct($user);
    $loyalty = app(LoyaltyPointsService::class);

    $loyalty->grantReviewBonus(moneyApprovedReview($user, $product, $order));
    $loyalty->grantReviewBonus(moneyApprovedReview($user, moneyProduct(), $order));

    expect($user->fresh()->points_balance)->toBe(40);
});

test('neither a live nor a deleted review can be followed by a second (BIZ-036)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    [$product, $order] = moneyReviewedProduct($user);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'price' => 100,
        'quantity' => 1, 'subtotal' => 100, 'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);
    $payload = ['rating' => 5, 'body' => 'A genuinely useful review.'];

    actingAs($user)->post(route('product.reviews.store', $product), $payload)->assertSessionHasNoErrors();
    actingAs($user)->post(route('product.reviews.store', $product), $payload)->assertSessionHasErrors('review');
    expect(Review::count())->toBe(1);

    Review::first()->delete();

    actingAs($user)->post(route('product.reviews.store', $product), $payload)->assertSessionHasErrors('review');
    expect(Review::count())->toBe(0)->and(Review::withTrashed()->count())->toBe(1);
});

test('a zero price is refused only where the price is really used (BIZ-020)', function () {
    $bundleRules = fn (string $type): array => (new ProductBundleRequest)->merge(['discount_type' => $type])->rules()['bundle_price'];
    $passes = fn (array $rules, array $data = ['bundle_price' => 0]): bool => Validator::make($data, ['bundle_price' => $rules])->passes();

    expect($passes($bundleRules('fixed_price')))->toBeFalse()
        ->and($passes($bundleRules('free_delivery')))->toBeTrue();
});

test('saving a product with variants ignores its hidden main price, but a plain product needs one (BIZ-020)', function () {
    $productRules = fn (array $input): array => (new ProductRequest)->merge($input)->rules()['price'];
    $passes = fn (array $rules): bool => Validator::make(['price' => 0], ['price' => $rules])->passes();

    expect($passes($productRules(['has_color_variant' => true, 'variants' => [['price' => 500]]])))->toBeTrue()
        ->and($passes($productRules([])))->toBeFalse();
});

test('stock locks are taken in id order, whatever order the lines are in (BIZ-022)', function () {
    $first = moneyProduct();
    $second = moneyProduct();
    $variantOwner = moneyProduct();
    $firstVariant = ProductVariant::factory()->create(['product_id' => $variantOwner->id]);
    $secondVariant = ProductVariant::factory()->create(['product_id' => $variantOwner->id]);

    DB::enableQueryLog();
    app(InventoryService::class)->lockStockInOrder([
        ['product_id' => $second->id, 'variant_id' => null],
        ['product_id' => $variantOwner->id, 'variant_id' => $secondVariant->id],
        ['product_id' => $first->id, 'variant_id' => null],
        ['product_id' => $variantOwner->id, 'variant_id' => $firstVariant->id],
    ]);
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries)->toHaveCount(2)
        ->and($queries[0])->toContain('"products"')->toContain('order by "id" asc')
        ->and($queries[1])->toContain('"product_variants"')->toContain('order by "id" asc');
});

test('a two-line order keeps its line order and takes the right stock (BIZ-022)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $first = moneyProduct(['stock_quantity' => 10]);
    $second = moneyProduct(['stock_quantity' => 10]);

    actingAs($user)->post(route('checkout.store'), moneyCheckoutPayload([
        'email' => $user->email, 'payment_method' => 'cod',
        'items' => [
            ['product_id' => $second->id, 'variant_id' => null, 'quantity' => 2, 'price' => 500],
            ['product_id' => $first->id, 'variant_id' => null, 'quantity' => 1, 'price' => 500],
        ],
    ]))->assertSessionHasNoErrors();

    expect(Order::sole()->items()->orderBy('id')->pluck('product_id')->all())->toBe([$second->id, $first->id])
        ->and($first->fresh()->stock_quantity)->toBe(9)
        ->and($second->fresh()->stock_quantity)->toBe(8);
});
