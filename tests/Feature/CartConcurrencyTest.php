<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\BundleService;
use App\Support\CartManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cartCheckoutPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => 'Cart Buyer',
        'email' => $user->email,
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

function cartRows(User $user): Collection
{
    return CartItem::query()->whereIn('cart_id', Cart::where('user_id', $user->id)->pluck('id'))->orderBy('id')->get();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function duplicateCartRows(User $user, Product $product, array $quantities, array $attributes = []): Cart
{
    $cart = Cart::firstOrCreate(['user_id' => $user->id]);

    foreach ($quantities as $quantity) {
        $cart->items()->create(array_merge(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price], $attributes));
    }

    return $cart;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->manager = app(CartManager::class);
    $this->product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
});

test('adding the same product twice keeps one row', function () {
    foreach ([1, 2] as $quantity) {
        $this->manager->syncUserCart($this->user, [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => $quantity]], merge: true);
    }

    expect(cartRows($this->user))->toHaveCount(1);
    expect(cartRows($this->user)->first()->quantity)->toBe(3);
});

test('adding the same variant twice keeps one row, and different variants stay apart', function () {
    $red = ProductVariant::factory()->create(['product_id' => $this->product->id, 'stock_quantity' => 5]);
    $blue = ProductVariant::factory()->create(['product_id' => $this->product->id, 'stock_quantity' => 5]);

    foreach ([$red, $red, $blue] as $variant) {
        $this->manager->syncUserCart($this->user, [['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 1]], merge: true);
    }

    expect(cartRows($this->user)->pluck('quantity', 'variant_id')->all())->toEqual([$red->id => 2, $blue->id => 1]);
});

test('a bundle line and a plain line of one product stay apart', function () {
    $bundle = ProductBundle::factory()->create();

    $this->manager->syncUserCart($this->user, [
        ['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 2, 'bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1],
    ]);

    expect(cartRows($this->user))->toHaveCount(2);
});

test('existing duplicates are collapsed on the next write, without losing quantity', function (bool $merge, int $expected) {
    duplicateCartRows($this->user, $this->product, [1, 2]);

    $this->manager->syncUserCart($this->user, [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]], merge: $merge);

    expect(cartRows($this->user))->toHaveCount(1);
    expect(cartRows($this->user)->first()->quantity)->toBe($expected);
})->with(['add' => [true, 4], 'sync to what the customer sees' => [false, 1]]);

test('collapsing duplicates never exceeds stock, or goes below the largest row', function (int $stock, array $rows, int $expected) {
    $this->product->update(['stock_quantity' => $stock]);
    duplicateCartRows($this->user, $this->product, $rows);

    $cart = $this->manager->withoutDuplicateRows(Cart::with('items')->where('user_id', $this->user->id)->firstOrFail());

    expect($cart->items)->toHaveCount(1);
    expect($cart->items->first()->quantity)->toBe($expected);
})->with([
    'within stock' => [5, [2, 2], 4],
    'capped at stock' => [3, [2, 2], 3],
    'keeps the largest row' => [1, [2, 2], 2],
]);

test('collapsing duplicates keeps variants and bundles apart', function () {
    $variant = ProductVariant::factory()->create(['product_id' => $this->product->id, 'stock_quantity' => 5]);
    $bundle = ProductBundle::factory()->create();
    $cart = duplicateCartRows($this->user, $this->product, [1, 1]);
    duplicateCartRows($this->user, $this->product, [1], ['variant_id' => $variant->id]);
    duplicateCartRows($this->user, $this->product, [1], ['bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1]);

    $this->manager->withoutDuplicateRows(Cart::with('items')->findOrFail($cart->id));

    expect(cartRows($this->user))->toHaveCount(3);
});

test('checkout charges one line when the customer submits one line', function () {
    duplicateCartRows($this->user, $this->product, [1, 1]);

    actingAs($this->user)->post(route('checkout.store'), cartCheckoutPayload($this->user, [
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->items()->count())->toBe(1);
    expect($order->items()->firstOrFail()->quantity)->toBe(1);
    expect((float) $order->subtotal)->toBe(100.0);
    expect($this->product->refresh()->stock_quantity)->toBe(9);
});

test('checkout without submitted items still makes one order line from duplicates', function () {
    duplicateCartRows($this->user, $this->product, [1, 1]);

    actingAs($this->user)->post(route('checkout.store'), cartCheckoutPayload($this->user))->assertRedirect();

    expect(Order::query()->firstOrFail()->items()->count())->toBe(1);
    expect($this->product->refresh()->stock_quantity)->toBe(8);
});

test('a cart changed during checkout is refused, and the same token can retry', function (string $paymentMethod) {
    $product = $this->product;
    $changeCart = true;

    // Runs where a concurrent write would land: after checkout has read the
    // cart, and before its order transaction.
    app()->bind(CartManager::class, fn ($app) => new class($app->make(BundleService::class), $product, $changeCart) extends CartManager
    {
        public function __construct(BundleService $bundles, private Product $product, private bool &$changeCart)
        {
            parent::__construct($bundles);
        }

        public function revalidatePrices(Cart $cart): bool
        {
            if ($this->changeCart) {
                $this->changeCart = false;
                CartItem::query()->where('product_id', $this->product->id)->update(['quantity' => 5]);
            }

            return parent::revalidatePrices($cart);
        }
    });

    $payload = cartCheckoutPayload($this->user, [
        'payment_method' => $paymentMethod,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]);

    actingAs($this->user)->post(route('checkout.store'), $payload)->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(10);

    actingAs($this->user)->post(route('checkout.store'), $payload)->assertSessionDoesntHaveErrors()->assertRedirect();

    expect(Cart::where('user_id', $this->user->id)->count())->toBeLessThanOrEqual(1);
})->with(['cod', 'mobile_banking']);

test('adding is capped at stock, and checkout still refuses a line stock fell below', function () {
    $this->product->update(['stock_quantity' => 1]);

    $this->manager->syncUserCart($this->user, [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 2]], merge: true);

    expect(cartRows($this->user)->first()->quantity)->toBe(1);

    $this->product->update(['stock_quantity' => 2]);
    $this->manager->syncUserCart($this->user, [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]], merge: true);
    $this->product->update(['stock_quantity' => 1]);

    expect(cartRows($this->user)->first()->quantity)->toBe(2);

    actingAs($this->user)->post(route('checkout.store'), cartCheckoutPayload($this->user))->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
    expect($this->product->refresh()->stock_quantity)->toBe(1);
});

test('a guest checkout still works', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);

    $this->post(route('checkout.store'), cartCheckoutPayload($this->user, [
        'email' => 'guest@example.com',
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();

    expect(Order::query()->whereNull('user_id')->count())->toBe(1);
});

test('login merges a guest cart without duplicating a line', function () {
    $sessionCart = Cart::create(['session_id' => 'guest-session']);
    $sessionCart->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'price' => 100]);
    duplicateCartRows($this->user, $this->product, [1]);

    $this->manager->mergeSessionCartIntoUserCart($this->user, 'guest-session');

    expect(cartRows($this->user))->toHaveCount(1);
    expect(cartRows($this->user)->first()->quantity)->toBe(3);
    expect(Cart::query()->whereKey($sessionCart->id)->exists())->toBeFalse();
});

test('two stale copies of a cart assemble a bundle only once', function () {
    $other = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $bundle = ProductBundle::factory()->create(['is_active' => true]);
    $bundle->items()->create(['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]);
    $bundle->items()->create(['product_id' => $other->id, 'variant_id' => null, 'quantity' => 1]);

    $cart = Cart::create(['user_id' => $this->user->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'price' => 100]);
    $cart->items()->create(['product_id' => $other->id, 'quantity' => 2, 'price' => 100]);

    $bundles = app(BundleService::class);
    $first = Cart::with('items.product')->findOrFail($cart->id);
    $second = Cart::with('items.product')->findOrFail($cart->id);

    $bundles->autoAssemble($first);
    $bundles->autoAssemble($second);

    $rows = cartRows($this->user);

    expect((int) $rows->sum('quantity'))->toBe(4);
    expect($rows->groupBy('product_id')->map->count()->max())->toBe(1);
});

test('a cart with nothing to assemble is not locked or written by a page view', function () {
    duplicateCartRows($this->user, $this->product, [1]);
    $cart = Cart::with('items.product')->where('user_id', $this->user->id)->firstOrFail();

    DB::enableQueryLog();
    $assembled = app(BundleService::class)->autoAssemble($cart);

    expect($assembled)->toBeFalse();
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query) => str_contains(strtolower($query), 'for update')))->toBeEmpty();
});
