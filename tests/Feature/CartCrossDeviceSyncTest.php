<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Support\CartManager;

/*
 * Cross-device cart sync. A device's full-cart save carries the version of
 * the server cart its items were based on (`base_version`), and the server
 * refuses it with 409 when the cart has changed since — otherwise a device
 * holding an older copy would delete lines another device added. The
 * `cart.state` poll tells a device whether its copy is still current.
 */
function crossDeviceCart(User $user, array $quantitiesByProductId): Cart
{
    $cart = Cart::create(['user_id' => $user->id, 'session_id' => 'device-test']);

    foreach ($quantitiesByProductId as $productId => $quantity) {
        $cart->items()->create([
            'product_id' => $productId,
            'variant_id' => null,
            'quantity' => $quantity,
            'price' => 100,
        ]);
    }

    return $cart;
}

function crossDeviceLine(Product $product, int $quantity): array
{
    return ['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity];
}

test('a save based on the current version is written and returns the new version', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = crossDeviceCart($user, [$product->id => 1]);
    $baseVersion = app(CartManager::class)->version($cart);

    $response = $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => $baseVersion,
        'items' => [crossDeviceLine($product, 3)],
    ]);

    $response->assertOk()
        ->assertJsonPath('cart.item_count', 3)
        ->assertJsonPath('cart.version', app(CartManager::class)->version($cart));
    expect($response->json('cart.version'))->not->toBe($baseVersion);
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3]);
});

test('a save based on an older version is refused with 409 and keeps the other device\'s line', function (): void {
    $user = User::factory()->create();
    $phoneProduct = Product::factory()->create();
    $pcProduct = Product::factory()->create();
    $cart = crossDeviceCart($user, [$phoneProduct->id => 1]);
    $phoneVersion = app(CartManager::class)->version($cart);

    // The PC adds a product after the phone loaded its copy.
    $cart->items()->create(['product_id' => $pcProduct->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]);

    // The phone, unaware of it, saves its own copy with a new quantity.
    $response = $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => $phoneVersion,
        'items' => [crossDeviceLine($phoneProduct, 2)],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('conflict', true)
        ->assertJsonPath('cart.item_count', 2)
        ->assertJsonPath('cart.version', app(CartManager::class)->version($cart));
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $pcProduct->id]);
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $phoneProduct->id, 'quantity' => 1]);
});

test('a change written by something other than a device save also makes an older version stale', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = crossDeviceCart($user, [$product->id => 1]);
    $oldVersion = app(CartManager::class)->version($cart);

    // e.g. checkout clean-up or the AI assistant changing the row directly.
    $cart->items()->update(['quantity' => 5]);

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => $oldVersion,
        'items' => [crossDeviceLine($product, 2)],
    ])->assertStatus(409);

    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 5]);
});

test('a device that saw no cart can create one, but not overwrite a cart another device created', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => CartManager::EMPTY_VERSION,
        'items' => [crossDeviceLine($product, 1)],
    ])->assertOk()->assertJsonPath('cart.item_count', 1);

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => CartManager::EMPTY_VERSION,
        'items' => [],
    ])->assertStatus(409);

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
});

test('a save without a base version is still written unconditionally', function (): void {
    $user = User::factory()->create();
    $kept = Product::factory()->create();
    $dropped = Product::factory()->create();
    $cart = crossDeviceCart($user, [$kept->id => 1, $dropped->id => 1]);

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'items' => [crossDeviceLine($kept, 1)],
    ])->assertOk();

    $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id, 'product_id' => $dropped->id]);
});

test('the login merge ignores a stale base version, since it only adds', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = crossDeviceCart($user, [$product->id => 1]);

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'merge' => true,
        'base_version' => 'stale-version',
        'items' => [crossDeviceLine($product, 2)],
    ])->assertOk();

    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3]);
});

test('an overlong base version is rejected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('cart.sync'), [
        'base_version' => str_repeat('a', 65),
        'items' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('base_version');
});

test('the cart state poll sends nothing when the device\'s version is current', function (): void {
    $user = User::factory()->create();
    $cart = crossDeviceCart($user, [Product::factory()->create()->id => 1]);
    $version = app(CartManager::class)->version($cart);

    $this->actingAs($user)->getJson(route('cart.state', ['version' => $version]))
        ->assertOk()
        ->assertExactJson(['changed' => false, 'version' => $version, 'wishlist_ids' => []]);
});

test('the cart state poll sends the current cart when the device\'s version is older', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = crossDeviceCart($user, [$product->id => 1]);
    $oldVersion = app(CartManager::class)->version($cart);
    $cart->items()->update(['quantity' => 4]);

    $this->actingAs($user)->getJson(route('cart.state', ['version' => $oldVersion]))
        ->assertOk()
        ->assertJsonPath('changed', true)
        ->assertJsonPath('cart.item_count', 4)
        ->assertJsonPath('version', app(CartManager::class)->version($cart));
});

test('the cart state poll reports an empty version when the customer has no cart', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('cart.state', ['version' => CartManager::EMPTY_VERSION]))
        ->assertOk()
        ->assertExactJson(['changed' => false, 'version' => CartManager::EMPTY_VERSION, 'wishlist_ids' => []]);
});

test('the cart state poll only ever returns the logged-in customer\'s own cart', function (): void {
    $other = User::factory()->create();
    crossDeviceCart($other, [Product::factory()->create()->id => 7]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('cart.state'))
        ->assertOk()
        ->assertJsonPath('changed', true)
        ->assertJsonPath('cart', null)
        ->assertJsonPath('version', CartManager::EMPTY_VERSION);
});

test('a guest cannot poll the cart state', function (): void {
    $this->getJson(route('cart.state'))->assertUnauthorized();
});
