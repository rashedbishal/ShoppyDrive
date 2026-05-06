<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;

test('guest cart items are merged into the authenticated cart on login sync', function () {
    $user = User::factory()->create();
    $serverProduct = Product::factory()->create(['price' => 120]);
    $guestProduct = Product::factory()->create(['price' => 80]);

    $cart = Cart::create([
        'user_id' => $user->id,
        'session_id' => 'persisted-session',
    ]);

    $cart->items()->create([
        'product_id' => $serverProduct->id,
        'variant_id' => null,
        'quantity' => 1,
        'price' => $serverProduct->price,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('cart.sync'), [
            'merge' => true,
            'items' => [
                [
                    'product_id' => $guestProduct->id,
                    'variant_id' => null,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $serverProduct->id,
                    'variant_id' => null,
                    'quantity' => 3,
                ],
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('cart.item_count', 6);

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $serverProduct->id,
        'quantity' => 4,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $guestProduct->id,
        'quantity' => 2,
    ]);
});

test('authenticated cart is restored from the database after logout and login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);
    $product = Product::factory()->create(['price' => 250]);

    $cart = Cart::create([
        'user_id' => $user->id,
        'session_id' => 'first-session',
    ]);

    $cart->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'quantity' => 2,
        'price' => $product->price,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $this->get(route('checkout.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('cart.item_count', 2)
            ->where('cart.items.0.product.id', $product->id)
            ->where('cart.items.0.quantity', 2)
        );
});
