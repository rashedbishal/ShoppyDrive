<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Tester report 63: a customer empties the cart while the checkout page stays
 * open (header cart, another tab, another device) and then presses Place
 * Order. The refusal must be an ordinary message, never the bare
 * "422 Unprocessable Content" error page.
 */
function emptyCartOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [],
    ], $overrides);
}

test('placing an order after the cart was emptied returns to checkout with a message instead of an error page', function () {
    $user = User::factory()->create();
    Cart::query()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), emptyCartOrderPayload(['email' => $user->email]))
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error', 'Your cart is empty. Add a product to your cart before placing an order.')
        ->assertSessionHasErrors(['items' => 'Your cart is empty. Add a product to your cart before placing an order.']);

    expect(Order::query()->count())->toBe(0);

    // The page it returns to then sends a customer with nothing to buy home.
    $this->actingAs($user)->get(route('checkout.index'))->assertRedirect(route('home'));
});

test('a guest placing an order with an emptied cart gets the same message', function () {
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);

    $this->from(route('checkout.index'))
        ->post(route('checkout.store'), emptyCartOrderPayload(['email' => 'guest@example.com']))
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHasErrors(['items' => 'Your cart is empty. Add a product to your cart before placing an order.']);

    expect(Order::query()->count())->toBe(0);
});

test('a submission without an items list falls back to the emptied saved cart and is refused the same way', function () {
    $user = User::factory()->create();
    Cart::query()->create(['user_id' => $user->id]);

    $payload = emptyCartOrderPayload(['email' => $user->email]);
    unset($payload['items']);

    $this->actingAs($user)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), $payload)
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});
