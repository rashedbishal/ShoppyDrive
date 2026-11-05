<?php

declare(strict_types=1);

/*
 * BE-002: a deleted product in the cart used to give a bare 404 at checkout
 * (fixed earlier by 8193cfb, which turned it into a cart issue). The issue
 * now also names the item, so the customer knows which one to remove.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
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

test('a product erased from the database entirely stays generic', function () {
    $response = $this->post(route('checkout.store'), unavailableItemPayload([
        ['product_id' => 999999, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
    ]));

    $response->assertSessionHas('cartIssues', ['An item in your cart is no longer available. Please remove it from your cart to continue.']);
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
