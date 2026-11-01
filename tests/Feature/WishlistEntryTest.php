<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('a guest gets a guest page instead of the account shell', function () {
    get(route('wishlist.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('account/GuestWishlist')->missing('wishlist'));
});

test('a signed-in customer is sent from the public link to their saved items', function () {
    $user = User::factory()->create();
    $saved = Product::factory()->create();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $saved->id]);

    actingAs($user)->get(route('wishlist.index'))->assertRedirect(route('account.wishlist'));

    actingAs($user)->get(route('account.wishlist'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('account/Wishlist')
        ->has('wishlist', 1)
        ->where('wishlist.0.id', $saved->id));
});

test('a customer sees only their own saved items', function () {
    $user = User::factory()->create();
    Wishlist::create(['user_id' => User::factory()->create()->id, 'product_id' => Product::factory()->create()->id]);

    actingAs($user)->get(route('account.wishlist'))->assertInertia(fn (AssertableInertia $page) => $page->has('wishlist', 0));
});

test('an unverified customer following the public link is asked to verify', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)->followingRedirects()->get(route('wishlist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/verify-email'));
});

test('signing in from the guest page returns the customer to their wishlist', function () {
    $user = User::factory()->create();

    get(route('account.wishlist'))->assertRedirect(route('login'));

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('account.wishlist'));
});

test('a customer can still toggle, sync, clear and move all items to the cart', function () {
    $user = User::factory()->create();
    $first = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay()]);
    $second = Product::factory()->create(['stock_quantity' => 10, 'published_at' => now()->subDay()]);

    actingAs($user)->postJson(route('wishlist.toggle'), ['product_id' => $first->id])
        ->assertOk()
        ->assertJson(['wishlisted' => true, 'wishlist_ids' => [$first->id]]);

    actingAs($user)->postJson(route('wishlist.sync'), ['ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('wishlist_ids', [$first->id, $second->id]);

    actingAs($user)->post(route('wishlist.add-all-to-cart'))->assertRedirect();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
    expect(Cart::where('user_id', $user->id)->firstOrFail()->items()->count())->toBe(2);

    Wishlist::create(['user_id' => $user->id, 'product_id' => $first->id]);

    actingAs($user)->post(route('wishlist.clear'))->assertRedirect();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
});

test('wishlist changes still require an account', function (string $route) {
    postJson(route($route), [])->assertUnauthorized();
})->with(['wishlist.toggle', 'wishlist.sync', 'wishlist.clear', 'wishlist.add-all-to-cart']);
