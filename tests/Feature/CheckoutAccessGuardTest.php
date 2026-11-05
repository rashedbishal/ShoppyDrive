<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;

test('visiting checkout with an empty cart and no buy-now item redirects to home', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)
        ->get(route('checkout.index'))
        ->assertRedirect(route('home'));
});

test('a guest reaches checkout with no server cart, since their cart is in the browser', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);

    $this->get(route('checkout.index'))->assertOk();
});

test('visiting checkout with items in the cart is allowed', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]);

    $this->actingAs($user)
        ->get(route('checkout.index'))
        ->assertOk();
});

test('visiting checkout with a valid buy-now item and an empty cart is allowed', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)
        ->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('buyNowItem.product.id', $product->id));
});

test('a buy-now item pointing at a non-existent product still redirects to home when the cart is also empty', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)
        ->get(route('checkout.index', ['buy_now_product' => 999999]))
        ->assertRedirect(route('home'));
});
