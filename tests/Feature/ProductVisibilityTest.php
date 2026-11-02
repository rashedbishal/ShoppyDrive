<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/*
 * FSA-05: inactive, draft (no published_at) and future-scheduled products
 * must be invisible to the storefront and impossible to buy. Checkout drops
 * a hidden line the same way it already drops a deleted product (see
 * CartStaleProductTest), so a stale client cart can't strand the customer.
 */

dataset('hidden product states', [
    'inactive' => [['is_active' => false, 'published_at' => now()->subDay()]],
    'draft' => [['is_active' => true, 'published_at' => null]],
    'future-scheduled' => [['is_active' => true, 'published_at' => now()->addDay()]],
]);

/**
 * @param  array<string, mixed>  $attributes
 */
function visibilityProduct(array $attributes = []): Product
{
    return Product::factory()->create([
        'price' => 400,
        'stock_quantity' => 10,
        'is_active' => true,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>|null  $items
 * @return array<string, mixed>
 */
function visibilityCheckoutPayload(?array $items): array
{
    $payload = [
        'name' => 'Visibility Buyer',
        'email' => 'visibility@example.com',
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ];

    return $items === null ? $payload : [...$payload, 'items' => $items];
}

test('an active, published product page is visible', function () {
    $this->get(route('product.show', visibilityProduct()->slug))->assertOk();
});

test('a hidden product page returns 404', function (array $state) {
    $this->get(route('product.show', visibilityProduct($state)->slug))->assertNotFound();
})->with('hidden product states');

test('a future-scheduled product becomes visible once its publish time passes', function () {
    $product = visibilityProduct(['published_at' => now()->addHour()]);

    $this->travel(2)->hours();

    $this->get(route('product.show', $product->slug))->assertOk();
});

test('add to cart rejects a hidden product', function (array $state) {
    $product = visibilityProduct($state);

    $this->actingAs(User::factory()->create())
        ->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasErrors(['product_id' => 'This product is not available.']);

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
})->with('hidden product states');

test('authenticated checkout of a hidden product creates no order', function (array $state) {
    $product = visibilityProduct($state);

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.store'), visibilityCheckoutPayload([
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ]))
        ->assertStatus(422);

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(10);
})->with('hidden product states');

test('guest checkout charges only the visible items and never sells the hidden one', function (array $state) {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
    $visible = visibilityProduct();
    $hidden = visibilityProduct($state);

    $this->post(route('checkout.store'), visibilityCheckoutPayload([
        ['product_id' => $visible->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $hidden->id, 'variant_id' => null, 'quantity' => 1],
    ]))->assertSessionHasNoErrors();

    $order = Order::with('items')->sole();

    expect($order->items->pluck('product_id')->all())->toBe([$visible->id])
        ->and($order->subtotal)->toBe(400.0)
        ->and($hidden->fresh()->stock_quantity)->toBe(10);
})->with('hidden product states');

test('a cart row for a product hidden after it was added cannot be checked out', function () {
    $user = User::factory()->create();
    $product = visibilityProduct();
    $cart = Cart::query()->create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 400]);

    $product->update(['is_active' => false]);

    $this->actingAs($user)->post(route('checkout.store'), visibilityCheckoutPayload(null))->assertStatus(422);

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('a buy now link to a hidden product leaves nothing to check out', function (array $state) {
    $product = visibilityProduct($state);

    $this->actingAs(User::factory()->create())
        ->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertRedirect(route('home'));
})->with('hidden product states');
