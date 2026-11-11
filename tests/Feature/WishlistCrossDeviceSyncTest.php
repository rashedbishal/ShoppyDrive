<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;

use function Pest\Laravel\actingAs;

/*
 * Cross-device wishlist sync. A heart click sends the state the customer
 * chose (`wishlisted`), so a device showing an older wishlist can't flip a
 * product the wrong way; the `cart.state` poll returns the account's
 * wishlist ids so other devices pick up changes without a page load.
 */
test('removing a product another device already removed keeps it removed', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id, 'wishlisted' => false])
        ->assertOk()
        ->assertExactJson(['wishlisted' => false, 'wishlist_ids' => []]);

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
});

test('adding a product another device already added keeps a single entry', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id, 'wishlisted' => true])
        ->assertOk()
        ->assertExactJson(['wishlisted' => true, 'wishlist_ids' => [$product->id]]);

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(1);
});

test('adding and removing with an explicit state change the saved wishlist', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id, 'wishlisted' => true])
        ->assertOk()
        ->assertJsonPath('wishlist_ids', [$product->id]);
    $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id, 'wishlisted' => false])
        ->assertOk()
        ->assertJsonPath('wishlist_ids', []);
    $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
});

test('a request without a state still toggles, for pages open across the deploy', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id])
        ->assertOk()
        ->assertJsonPath('wishlisted', true);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id])
        ->assertOk()
        ->assertJsonPath('wishlisted', false);
});

test('a non-boolean wishlisted state is rejected', function (): void {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $product->id, 'wishlisted' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wishlisted');
});

test('the sync poll returns only the logged-in customer\'s own wishlist ids', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Product::factory()->create();
    $theirs = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $mine->id]);
    Wishlist::create(['user_id' => $other->id, 'product_id' => $theirs->id]);

    actingAs($user)->getJson(route('cart.state'))
        ->assertOk()
        ->assertJsonPath('wishlist_ids', [$mine->id]);
});
