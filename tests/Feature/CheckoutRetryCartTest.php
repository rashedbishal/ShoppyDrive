<?php

declare(strict_types=1);

/*
 * BIZ-014: a retried checkout (same order_token, e.g. a network retry) made
 * no second order but re-filled the cart with the items just bought,
 * because the replay check ran after the cart sync.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

function retryCheckoutPayload(Product $product, string $method, string $token): array
{
    return [
        'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01700000000', 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => $method, 'order_token' => $token,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => (float) $product->price]],
    ];
}

test('a retried COD checkout goes to the same order and leaves the cart empty', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();
    $product = Product::factory()->create(['price' => 100]);
    $payload = retryCheckoutPayload($product, 'cod', (string) Str::uuid());

    $first = $this->actingAs($user)->post(route('checkout.store'), $payload);
    $second = $this->actingAs($user)->post(route('checkout.store'), $payload);

    $order = Order::sole();
    $first->assertRedirect(route('order.confirmation', $order->order_number));
    $second->assertRedirect(route('order.confirmation', $order->order_number));
    expect(Cart::where('user_id', $user->id)->withCount('items')->first()?->items_count ?? 0)->toBe(0)
        ->and($product->refresh()->stock_quantity)->toBe($product->stock_quantity);
});

test('a retried mobile-banking checkout returns to the same payment page and leaves the cart as it was', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();
    $product = Product::factory()->create(['price' => 100]);
    $payload = retryCheckoutPayload($product, 'mobile_banking', (string) Str::uuid());

    $first = $this->actingAs($user)->post(route('checkout.store'), $payload);
    $second = $this->actingAs($user)->post(route('checkout.store'), $payload);

    // Mobile-banking carts are only cleared once the payment is submitted
    // (MobilePaymentController), so the cart still holds the one line.
    $cart = Cart::where('user_id', $user->id)->with('items')->first();
    expect(PendingCheckout::count())->toBe(1)
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->quantity)->toBe(1);
});
