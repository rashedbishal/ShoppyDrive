<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

function wishlistCheckoutPayload(User $user): array
{
    return [
        'name' => 'Wishlist Buyer', 'email' => $user->email, 'phone' => '01700000000', 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
    ];
}

function wishlistVariantProduct(): Product
{
    $product = Product::factory()->create(['stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay()]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true, 'stock_quantity' => 10]);

    return $product;
}

function saveWishlistProducts(User $user, int $simple = 0, int $variant = 0, int $deleted = 0): void
{
    $products = collect()
        ->merge(Product::factory()->count($simple)->create(['stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay()]))
        ->merge(collect(range(1, $variant))->filter(fn () => $variant > 0)->map(fn () => wishlistVariantProduct()));
    $removed = Product::factory()->count($deleted)->create();

    foreach ($products->merge($removed) as $product) {
        Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    $removed->each->delete();
}

test('toggling a saved product removes it', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id])
        ->assertOk()
        ->assertExactJson(['wishlisted' => false, 'wishlist_ids' => []]);

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
});

test('toggling only affects the signed-in customer', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->create();
    Wishlist::create(['user_id' => $other->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id])->assertJson(['wishlisted' => true]);

    expect(Wishlist::where('user_id', $other->id)->count())->toBe(1);
});

test('toggle refuses a missing or unknown product', function (array $payload) {
    actingAs(User::factory()->create())->postJson(route('wishlist.toggle'), $payload)->assertUnprocessable()->assertJsonValidationErrors('product_id');
})->with(['missing' => [[]], 'unknown' => [['product_id' => 999999]], 'not a number' => [['product_id' => 'abc']]]);

test('sync adds nothing twice and returns the whole list', function () {
    $user = User::factory()->create();
    $saved = Product::factory()->create();
    $new = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $saved->id]);

    actingAs($user)->postJson(route('wishlist.sync'), ['ids' => [$saved->id, $new->id, $new->id]])->assertOk();

    expect(Wishlist::where('user_id', $user->id)->pluck('product_id')->sort()->values()->all())->toBe([$saved->id, $new->id]);
});

test('sync refuses bad input and saves nothing', function (Closure $ids) {
    $user = User::factory()->create();

    actingAs($user)->postJson(route('wishlist.sync'), ['ids' => $ids()])->assertUnprocessable();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
})->with([
    'not a list' => [fn () => 'nope'],
    'unknown product' => [fn () => [999999]],
    'over the limit' => [fn () => range(1, 201)],
]);

test('clearing leaves other customers alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
    Wishlist::create(['user_id' => $other->id, 'product_id' => $product->id]);

    actingAs($user)->post(route('wishlist.clear'))->assertRedirect();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
    expect(Wishlist::where('user_id', $other->id)->count())->toBe(1);
});

test('moving an empty wishlist to the cart creates no cart', function () {
    $user = User::factory()->create();

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertRedirect();

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

test('moving items adds to what is already in the cart and leaves other customers alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay()]);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => $product->price]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
    Wishlist::create(['user_id' => $other->id, 'product_id' => $product->id]);

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertRedirect();

    expect($cart->items()->sole()->quantity)->toBe(3);
    expect(Wishlist::where('user_id', $other->id)->count())->toBe(1);
});

test('moving items answers JSON callers with the cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay()]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.add-all-to-cart'))
        ->assertOk()
        ->assertJsonPath('message', 'All items added to cart')
        ->assertJsonPath('cart.item_count', 1);
});

test('an unverified customer can use wishlist actions', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => Product::factory()->create()->id])->assertOk();
});

test('the wishlist page skips a product that has been deleted', function () {
    $user = User::factory()->create();
    $kept = Product::factory()->create();
    $deleted = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $kept->id]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $deleted->id]);
    $deleted->delete();

    actingAs($user)->get(route('account.wishlist'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('wishlist', 1)->where('wishlist.0.id', $kept->id));
});

test('the wishlist page shows a saved product\'s active flash-sale price, not its regular one', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 2000]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    $flashSale = FlashSale::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 1000,
    ]);

    actingAs($user)->get(route('account.wishlist'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wishlist.0.in_flash_sale', true)
            ->where('wishlist.0.flash_sale_price', 1000)
            ->where('wishlist.0.price', 2000));
});

test('moving items still works when a saved product has been deleted', function () {
    $user = User::factory()->create();
    $kept = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay()]);
    $deleted = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $kept->id]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $deleted->id]);
    $deleted->delete();

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertRedirect()->assertSessionHas('info');

    expect(Cart::where('user_id', $user->id)->firstOrFail()->items()->pluck('product_id')->all())->toBe([$kept->id]);
    expect(Wishlist::where('user_id', $user->id)->pluck('product_id')->all())->toBe([$deleted->id]);
});

test('a variant product stays on the wishlist and never reaches the cart', function () {
    $user = User::factory()->create();
    $product = wishlistVariantProduct();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertRedirect()->assertSessionHas('info', fn (string $message) => str_contains($message, 'size or colour'));

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
    expect(Wishlist::where('user_id', $user->id)->pluck('product_id')->all())->toBe([$product->id]);
});

test('add-all reports exactly what happened', function (Closure $arrange, array $counts, string $message, ?int $cartLines) {
    $user = User::factory()->create();
    $arrange($user);

    $response = actingAs($user)->postJson(route('wishlist.add-all-to-cart'))->assertOk();

    $response->assertJson(['added' => $counts[0], 'skipped_needs_options' => $counts[1], 'skipped_unavailable' => $counts[2]]);
    $response->assertJsonPath('message', $message);

    expect($response->json('cart.items') === null ? null : count($response->json('cart.items')))->toBe($cartLines);
})->with([
    'empty' => [fn () => null, [0, 0, 0], 'Your wishlist is empty.', null],
    'only simple' => [fn (User $user) => saveWishlistProducts($user, simple: 2), [2, 0, 0], 'All items added to cart', 2],
    'only variant' => [
        fn (User $user) => saveWishlistProducts($user, variant: 1),
        [0, 1, 0],
        '1 item needs a size or colour chosen on the product page, so it stayed on your wishlist.',
        null,
    ],
    'only deleted' => [
        fn (User $user) => saveWishlistProducts($user, deleted: 2),
        [0, 0, 2],
        '2 items are no longer available, so they were not added.',
        null,
    ],
    'mixed' => [
        fn (User $user) => saveWishlistProducts($user, simple: 1, variant: 2, deleted: 1),
        [1, 2, 1],
        '1 item added to your cart. 2 items need a size or colour chosen on the product page, so they stayed on your wishlist. 1 item is no longer available, so it was not added.',
        1,
    ],
]);

test('an unlisted product is skipped and kept', function (array $attributes) {
    $user = User::factory()->create();
    $product = Product::factory()->create($attributes);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.add-all-to-cart'))->assertJson(['added' => 0, 'skipped_unavailable' => 1]);

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(1);
})->with([
    'inactive' => [['is_active' => false, 'published_at' => now()->subDay()]],
    'unpublished' => [['is_active' => true, 'published_at' => null]],
    'scheduled' => [['is_active' => true, 'published_at' => now()->addDay()]],
]);

test('a mixed wishlist merges with the cart, and running it again changes nothing', function () {
    $user = User::factory()->create();
    $simple = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay(), 'is_active' => true]);
    $variantProduct = wishlistVariantProduct();
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $simple->id, 'quantity' => 2, 'price' => $simple->price]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $simple->id]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $variantProduct->id]);

    actingAs($user)->post(route('wishlist.add-all-to-cart'));
    actingAs($user)->postJson(route('wishlist.add-all-to-cart'))->assertJson(['added' => 0, 'skipped_needs_options' => 1]);

    expect($cart->items()->sole()->quantity)->toBe(3);
    expect(Wishlist::where('user_id', $user->id)->pluck('product_id')->all())->toBe([$variantProduct->id]);
});

test('a failed cart write keeps the whole wishlist', function () {
    $user = User::factory()->create();
    saveWishlistProducts($user, simple: 2);
    CartItem::creating(fn () => throw new RuntimeException('Cart write failed.'));

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertServerError();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(2);
    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

test('a failed wishlist removal keeps the cart unchanged', function () {
    $user = User::factory()->create();
    saveWishlistProducts($user, simple: 1);
    Event::listen(QueryExecuted::class, function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'delete from "wishlists"') || str_starts_with(strtolower($query->sql), 'delete from `wishlists`')) {
            throw new RuntimeException('Wishlist delete failed.');
        }
    });

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertServerError();

    expect(CartItem::query()->count())->toBe(0);
    expect(Wishlist::where('user_id', $user->id)->count())->toBe(1);
});

test('a simple product added from the wishlist can be checked out', function () {
    $user = User::factory()->create();
    saveWishlistProducts($user, simple: 1);

    actingAs($user)->post(route('wishlist.add-all-to-cart'));
    actingAs($user)->post(route('checkout.store'), wishlistCheckoutPayload($user))->assertSessionHasNoErrors();

    expect(Order::where('user_id', $user->id)->count())->toBe(1);
});

test('out of stock simple products are still left to checkout to refuse', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 0, 'is_active' => true, 'published_at' => now()->subDay()]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->post(route('wishlist.add-all-to-cart'));
    actingAs($user)->post(route('checkout.store'), wishlistCheckoutPayload($user))->assertSessionHasErrors('items');

    expect(Order::where('user_id', $user->id)->count())->toBe(0);
});

test('an out-of-stock product stays on the wishlist and never reaches the cart', function () {
    $user = User::factory()->create();
    $inStock = Product::factory()->create(['stock_quantity' => 5, 'is_active' => true, 'published_at' => now()->subDay()]);
    $soldOut = Product::factory()->create(['stock_quantity' => 0, 'is_active' => true, 'published_at' => now()->subDay()]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $inStock->id]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $soldOut->id]);

    actingAs($user)->postJson(route('wishlist.add-all-to-cart'))
        ->assertOk()
        ->assertJson(['added' => 1, 'skipped_needs_options' => 0, 'skipped_out_of_stock' => 1, 'skipped_unavailable' => 0])
        ->assertJsonPath('message', '1 item added to your cart. 1 item is out of stock, so it stayed on your wishlist.');

    expect(Cart::where('user_id', $user->id)->firstOrFail()->items()->pluck('product_id')->all())->toBe([$inStock->id]);
    expect(Wishlist::where('user_id', $user->id)->pluck('product_id')->all())->toBe([$soldOut->id]);
});

test('a wishlist of only out-of-stock products adds nothing and says so', function () {
    $user = User::factory()->create();
    foreach (Product::factory()->count(2)->create(['stock_quantity' => 0, 'is_active' => true, 'published_at' => now()->subDay()]) as $product) {
        Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    actingAs($user)->post(route('wishlist.add-all-to-cart'))
        ->assertRedirect()
        ->assertSessionHas('info', '2 items are out of stock, so they stayed on your wishlist.');

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
    expect(Wishlist::where('user_id', $user->id)->count())->toBe(2);
});
