<?php

declare(strict_types=1);

/*
 * BE-002: a deleted product in the cart used to give a bare 404 at checkout
 * (fixed earlier by 8193cfb, which turned it into a cart issue). The issue
 * now also names the item, so the customer knows which one to remove.
 */

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\CartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function unavailableItemPayload(array $items): array
{
    return [
        'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '01700000000', 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(), 'items' => $items,
    ];
}

beforeEach(function () {
    Setting::create(['key' => 'guest_checkout_enabled', 'value' => true, 'group' => 'general']);
});

test('a deleted product in a guest cart is named, not a 404', function () {
    $product = Product::factory()->create(['name' => 'Blue Cotton T-Shirt', 'price' => 100]);
    $product->delete();

    $response = $this->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
    ]));

    $response->assertRedirect()
        ->assertSessionHas('cartIssues', ['Blue Cotton T-Shirt is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
});

test('a product erased from the database entirely stays generic when nothing else names it', function () {
    $response = $this->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => 999999, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
    ]));

    $response->assertSessionHas('cartIssues', ['An item in your cart is no longer available. Please remove it from your cart to continue.']);
});

/*
 * BE-002 retest (2026-11-09): the real admin deletion path
 * (Admin\ProductController::destroy()) always force-deletes, not
 * soft-deletes — the test above used $product->delete() (soft), which
 * withTrashed() can still find, so it never actually exercised this. Once a
 * product is truly force-deleted there is nothing left in the database to
 * name it with at all, so the server now falls back to whatever name
 * Checkout.tsx was already displaying and submitted alongside the id.
 */
test('a force-deleted product in a guest cart is still named, using the name the page itself submitted', function () {
    $product = Product::factory()->create(['name' => 'Blue Cotton T-Shirt', 'price' => 100]);
    $product->forceDelete();

    $response = $this->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100, 'product_name' => 'Blue Cotton T-Shirt'],
    ]));

    $response->assertSessionHas('cartIssues', ['Blue Cotton T-Shirt is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
});

test('a force-deleted product with no submitted name at all stays generic, never a 404', function () {
    $product = Product::factory()->create(['name' => 'Blue Cotton T-Shirt', 'price' => 100]);
    $product->forceDelete();

    $response = $this->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
    ]));

    $response->assertSessionHas('cartIssues', ['An item in your cart is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
});

test('a deleted product in a logged-in customer\'s saved cart is named too', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Leather Wallet', 'price' => 100]);
    Cart::query()->where('user_id', $user->id)->delete();
    Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]);
    $product->delete();

    $payload = unavailableItemPayload([]);
    unset($payload['items']);

    $this->actingAs($user)->post(route('checkout.store'), $payload)
        ->assertSessionHas('cartIssues', ['Leather Wallet is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
});

/*
 * BE-002 retest (2026-11-09): the real Checkout.tsx always resubmits its
 * full items list on every placement — never an empty/omitted `items` key
 * (the prior test's `unset($payload['items'])` was the only path the
 * original fix actually covered). A deleted item is invisible to the
 * client (validItems() hides it from `cart`), so the client can only ever
 * resubmit what it can see — meaning this unavailable item is always
 * naturally absent from a real resubmission too, not because the customer
 * chose to drop it. CartManager::replaceItems() used to delete any
 * existing row not in the resubmitted list regardless of why it was
 * missing, erasing the only evidence of the problem before
 * collectCartIssues() ever got to see it — checkout silently proceeded
 * with the item just gone, no cart issue at all.
 */
test('a realistic resubmission that naturally omits the invisible deleted item still blocks checkout and names it', function () {
    $user = User::factory()->create();
    $productA = Product::factory()->create(['name' => 'Leather Wallet', 'price' => 100]);
    $productB = Product::factory()->create(['name' => 'Cotton Socks', 'price' => 50]);

    app(CartManager::class)->syncUserCart($user, [
        ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
    ]);
    $productA->forceDelete();

    // Realistic: the client never knew productA existed in this cart (it
    // was already filtered out of the `cart` prop), so it only resubmits
    // productB — exactly as Checkout.tsx actually behaves.
    $response = $this->actingAs($user)->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1, 'price' => 50],
    ]));

    $response->assertSessionHas('cartIssues', ['Leather Wallet is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
    expect(CartItem::where('cart_id', Cart::where('user_id', $user->id)->value('id'))->where('product_id', $productB->id)->exists())
        ->toBeTrue('the still-available item must not be affected');
});

/*
 * BE-002 retest (2026-11-09): a force-deleted product's cart_item row used
 * to be destroyed outright by product_id's old cascadeOnDelete FK — the
 * item just vanished from the customer's saved cart with nothing to show
 * for it, worse than the guest case's generic message. product_id is now
 * nullOnDelete and the row keeps a product_name snapshot (written by
 * CartManager on every real write, exercised here via syncUserCart() the
 * same way checkout itself does), so it survives and is still named.
 */
test('a force-deleted product in a logged-in customer\'s saved cart survives and is named, not silently dropped', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Leather Wallet', 'price' => 100]);
    Cart::query()->where('user_id', $user->id)->delete();

    app(CartManager::class)->syncUserCart($user, [
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
    ]);
    $itemId = CartItem::where('product_id', $product->id)->value('id');

    $product->forceDelete();

    expect(CartItem::find($itemId))->not->toBeNull('the cart_item row must survive the product being force-deleted');
    expect(CartItem::find($itemId)->product_id)->toBeNull();

    $payload = unavailableItemPayload([]);
    unset($payload['items']);

    $this->actingAs($user)->post(route('checkout.store'), $payload)
        ->assertSessionHas('cartIssues', ['Leather Wallet is no longer available. Please remove it from your cart to continue.']);
    expect(Order::count())->toBe(0);
});
