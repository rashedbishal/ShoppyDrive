<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function buyNowOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'address_line2' => 'Flat 3B',
        // "Inside Dhaka City Corporation" is the magic value the checkout
        // form's district/city picker sends for free shipping — plain
        // "Dhaka" as the city does not match and falls back to the paid rate.
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('a guest clicking buy now is redirected to login instead of the checkout page', function () {
    $product = Product::factory()->create(['price' => 500]);

    $response = $this->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_qty' => 1]));

    $response->assertRedirect(route('login'));
});

test('checkout page resolves a buy-now item from the query string without creating a cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)
        ->get(route('checkout.index', ['buy_now_product' => $product->id, 'buy_now_qty' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('buyNowItem.product.id', $product->id)
            ->where('buyNowItem.quantity', 2)
            ->where('buyNowItem.price', 500)
            ->where('buyNowItem.subtotal', 1000)
        );

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

test('buy now places an order without ever creating a cart row', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 299]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number, false));
    expect($order->is_buy_now)->toBeTrue();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'user_id' => $user->id,
        'subtotal' => 598.00,
        'shipping_cost' => 60,
        'total' => 658.00,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'subtotal' => 598.00,
    ]);

    expect(Cart::where('user_id', $user->id)->exists())->toBeFalse();
});

/**
 * Gives $user a saved cart holding one line.
 */
function cartHolding(User $user, Product $product, int $quantity): Cart
{
    Cart::query()->where('user_id', $user->id)->delete();

    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity, 'price' => $product->price]);

    return $cart;
}

test('a buy-now order is only that product and leaves the cart exactly as it was', function () {
    $user = User::factory()->create();
    $cartedProduct = Product::factory()->create(['price' => 150, 'stock_quantity' => 20]);
    $buyNowProduct = Product::factory()->create(['price' => 299, 'stock_quantity' => 20]);
    $cart = cartHolding($user, $cartedProduct, 3);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $buyNowProduct->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->is_buy_now)->toBeTrue()
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->value('product_id'))->toBe($buyNowProduct->id)
        ->and((float) $order->subtotal)->toBe(299.0);

    // The cart still holds the same line, and no leftover basket remains.
    expect(Cart::query()->count())->toBe(1)
        ->and($cart->fresh()->items()->count())->toBe(1)
        ->and($cart->fresh()->items()->first()->product_id)->toBe($cartedProduct->id)
        ->and($cart->fresh()->items()->first()->quantity)->toBe(3);
});

test('buying a product now that is also in the cart buys only the buy-now quantity and keeps the cart line', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 20]);
    $cart = cartHolding($user, $product, 3);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->items()->sole()->quantity)->toBe(1)
        ->and((float) $order->subtotal)->toBe(100.0)
        ->and($cart->fresh()->items()->sole()->quantity)->toBe(3)
        ->and($product->fresh()->stock_quantity)->toBe(19);
});

test('a normal checkout from the cart still orders every cart item and empties the cart', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 20]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 20]);
    $cart = cartHolding($user, $a, 2);
    $cart->items()->create(['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1, 'price' => 50]);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'items' => [
            ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 2],
            ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]))->assertSessionHasNoErrors();

    $order = Order::query()->sole();
    expect($order->is_buy_now)->toBeFalse()
        ->and($order->items()->count())->toBe(2)
        ->and(Cart::query()->where('id', $cart->id)->exists())->toBeFalse();
});

test('a refused buy-now (out of stock) leaves the cart untouched and no temporary basket behind', function () {
    $user = User::factory()->create();
    $cartedProduct = Product::factory()->create(['price' => 150, 'stock_quantity' => 20]);
    $soldOut = Product::factory()->create(['price' => 99, 'stock_quantity' => 0]);
    $cart = cartHolding($user, $cartedProduct, 2);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $soldOut->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertSessionHas('cartIssues');

    expect(Order::query()->count())->toBe(0)
        ->and(Cart::query()->count())->toBe(1)
        ->and($cart->fresh()->items()->count())->toBe(1)
        ->and($cart->fresh()->items()->sole()->quantity)->toBe(2);
});

test('a stale buy-now price is refused without touching the cart or sending cart price updates', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 20]);
    $cart = cartHolding($user, Product::factory()->create(['price' => 10, 'stock_quantity' => 20]), 1);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 150]],
    ]))->assertSessionHas('error')->assertSessionMissing('priceUpdates');

    expect(Order::query()->count())->toBe(0)
        ->and(Cart::query()->count())->toBe(1)
        ->and($cart->fresh()->items()->count())->toBe(1);
});

test('a buy-now submission with more than one line is refused', function () {
    $user = User::factory()->create();
    [$a, $b] = Product::factory()->count(2)->create(['price' => 100, 'stock_quantity' => 20])->all();

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [
            ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]))->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0)->and(Cart::query()->count())->toBe(0);
});

test('submitting the same buy-now twice places one order and leaves the cart alone', function () {
    $user = User::factory()->create();
    $cartedProduct = Product::factory()->create(['price' => 150, 'stock_quantity' => 20]);
    $buyNowProduct = Product::factory()->create(['price' => 99, 'stock_quantity' => 20]);
    $cart = cartHolding($user, $cartedProduct, 1);
    $payload = buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $buyNowProduct->id, 'variant_id' => null, 'quantity' => 1]],
    ]);

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and(Cart::query()->count())->toBe(1)
        ->and($cart->fresh()->items()->sole()->product_id)->toBe($cartedProduct->id);
});

test('a guest buy-now with guest checkout on places the order and leaves no basket behind', function () {
    Setting::create(['key' => 'guest_checkout_enabled', 'value' => true, 'group' => 'general']);
    $product = Product::factory()->create(['price' => 120, 'stock_quantity' => 20]);

    $this->post(route('checkout.store'), buyNowOrderPayload([
        'buy_now' => true,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertSessionHasNoErrors();

    expect(Order::query()->sole()->is_buy_now)->toBeTrue()->and(Cart::query()->count())->toBe(0);
});

test('a paid mobile-banking buy-now order is flagged, and the real cart is never touched', function () {
    $user = User::factory()->create();
    $cartedProduct = Product::factory()->create(['price' => 150, 'stock_quantity' => 20]);
    $buyNowProduct = Product::factory()->create(['price' => 200, 'stock_quantity' => 20]);
    $cart = cartHolding($user, $cartedProduct, 2);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'payment_method' => 'mobile_banking',
        'buy_now' => true,
        'items' => [['product_id' => $buyNowProduct->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();

    $pending = PendingCheckout::query()->sole();
    expect($pending->cart_id)->toBeNull()
        ->and($pending->checkout_data['is_buy_now'])->toBeTrue()
        ->and(Cart::query()->count())->toBe(1);

    ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => 'TrxID BUYNOW1',
        'received_at' => now(), 'parsed_transaction_id' => 'BUYNOW1',
        'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->actingAs($user)
        ->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'BUYNOW1'])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    $order = Order::query()->sole();
    expect($order->is_buy_now)->toBeTrue()
        ->and($order->items()->sole()->product_id)->toBe($buyNowProduct->id)
        ->and($cart->fresh()->items()->sole()->quantity)->toBe(2);
});

test('the confirmation page tells the browser to keep the cart after a buy-now order only', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 20]);

    $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
        'buy_now' => true,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));
    $buyNowOrder = Order::query()->sole();

    $this->actingAs($user)->get(route('order.confirmation', $buyNowOrder->order_number))
        ->assertInertia(fn (Assert $page) => $page->where('keepCart', true));

    $cartOrder = Order::create([
        'order_number' => 'ORD-CART-1', 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'X'], 'billing_address' => [],
    ]);

    $this->actingAs($user)->get(route('order.confirmation', $cartOrder->order_number))
        ->assertInertia(fn (Assert $page) => $page->where('keepCart', false));
});

test('checkout with no items and no existing cart is rejected', function () {
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), buyNowOrderPayload([
        'email' => $user->email,
    ]));

    $response->assertStatus(422);
    expect(Order::query()->count())->toBe(0);
});
