<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function cartWithLiveAndStaleItems(User $user): array
{
    $live = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 100]);
    $stale = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 50]);

    $cart = Cart::create(['user_id' => $user->id, 'session_id' => 'test-session']);
    $cart->items()->create(['product_id' => $live->id, 'variant_id' => null, 'quantity' => 1, 'price' => $live->price]);
    $cart->items()->create(['product_id' => $stale->id, 'variant_id' => null, 'quantity' => 1, 'price' => $stale->price]);

    $stale->delete();

    return [$cart, $live];
}

test('shared cart prop drops a stale item instead of crashing every page', function () {
    $user = User::factory()->create();
    [, $live] = cartWithLiveAndStaleItems($user);

    $response = actingAs($user)->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('cart.items', 1)
        ->where('cart.items.0.product.id', $live->id)
        ->where('cart.item_count', 1)
        ->where('cart.subtotal', fn ($subtotal) => (float) $subtotal === (float) $live->price)
    );
});

/*
 * A mixed cart (one live item, one stale) must block checkout entirely
 * with a clear reason, not silently place the order using only the live
 * item — the customer has to go fix their cart themselves (owner decision,
 * 2026-11-05: "block entirely until cart is fixed", see
 * CheckoutController::collectCartIssues()).
 */
test('placing a cod order with a stale cart item is refused, not silently placed with only the live item', function () {
    $user = User::factory()->create();
    [, $live] = cartWithLiveAndStaleItems($user);

    $response = actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ]);

    $response->assertRedirect();
    expect($response->getSession()->get('cartIssues'))->not->toBeEmpty();
    expect(Order::query()->count())->toBe(0);
    expect($live->refresh())->not->toBeNull(); // nothing was ordered or touched
});

test('placing an order with only a stale cart item is refused the same way', function () {
    $user = User::factory()->create();
    $stale = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $cart = Cart::create(['user_id' => $user->id, 'session_id' => 'test-session']);
    $cart->items()->create(['product_id' => $stale->id, 'variant_id' => null, 'quantity' => 1, 'price' => $stale->price]);
    $stale->delete();

    $response = actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ]);

    $response->assertRedirect();
    expect($response->getSession()->get('cartIssues'))->not->toBeEmpty();
    expect(Order::query()->count())->toBe(0);
});

/*
 * The exact tester report: a product already added to the cart gets
 * hard-deleted by an admin, and the browser's stale Checkout.tsx state
 * still submits an explicit `items` array naming it (not the legacy,
 * items-omitted path the tests above cover) — this must be refused with a
 * clear reason too, not a 404/validation failure the frontend can't show.
 */
test('submitting an explicit items array naming a since-hard-deleted product is refused with a clear reason, not a silent failure', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 200]);
    $productId = $product->id;
    $product->forceDelete();

    $response = actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $productId, 'variant_id' => null, 'quantity' => 1, 'price' => 200]],
    ]);

    $response->assertRedirect();
    expect($response->getSession()->get('cartIssues'))->not->toBeEmpty();
    expect(Order::query()->count())->toBe(0);
});

test('an in-stock-when-added product that sold out by checkout is refused, not silently placed at a reduced quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 150, 'stock_quantity' => 1]);

    $response = actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5, 'price' => 150]],
    ]);

    $response->assertRedirect();
    $issues = $response->getSession()->get('cartIssues');
    expect($issues)->not->toBeEmpty();
    expect($issues[0])->toContain('1 unit(s)');
    expect(Order::query()->count())->toBe(0);
});
