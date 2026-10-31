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

test('placing a cod order with a stale cart item succeeds using only the live item', function () {
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

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number, false));

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->product_id)->toBe($live->id);
    expect((float) $order->subtotal)->toBe((float) $live->price);
});

test('placing an order with only a stale cart item is rejected as an empty cart', function () {
    $user = User::factory()->create();
    $stale = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $cart = Cart::create(['user_id' => $user->id, 'session_id' => 'test-session']);
    $cart->items()->create(['product_id' => $stale->id, 'variant_id' => null, 'quantity' => 1, 'price' => $stale->price]);
    $stale->delete();

    actingAs($user)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ])->assertStatus(422);
});
