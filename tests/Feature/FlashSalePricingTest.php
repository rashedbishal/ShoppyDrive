<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReceivedSms;
use App\Models\User;
use App\Support\CartManager;
use Illuminate\Support\Str;

function checkoutPayload(Product $product, int $quantity, array $overrides = []): array
{
    return array_merge([
        'name' => 'Flash Pricing Test',
        'email' => 'flash@example.com',
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity],
        ],
    ], $overrides);
}

test('an active flash sale is charged at the sale price', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    expect(app(CartManager::class)->resolvePrice($product->id, null))->toBe(150.0);
});

test('an expired flash sale no longer applies', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'is_active' => true]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    expect(app(CartManager::class)->resolvePrice($product->id, null))->toBe(200.0);
});

test('a flash sale that has not started yet does not apply', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'is_active' => true]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    expect(app(CartManager::class)->resolvePrice($product->id, null))->toBe(200.0);
});

test('an inactive flash sale campaign does not apply even within its date window', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => false]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    expect(app(CartManager::class)->resolvePrice($product->id, null))->toBe(200.0);
});

test('a product not on any flash sale keeps its ordinary price', function () {
    $onSale = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $notOnSale = Product::factory()->create(['price' => 75, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $onSale->id, 'sale_price' => 150]);

    expect(app(CartManager::class)->resolvePrice($notOnSale->id, null))->toBe(75.0);
});

test('a variant-specific flash sale item takes priority over a product-wide one for that variant', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => null, 'sale_price' => 150]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 120]);

    expect(app(CartManager::class)->resolvePrice($product->id, $variant->id))->toBe(120.0);
    // A different variant of the same product still only matches the product-wide item.
    $otherVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 200, 'stock_quantity' => 10]);
    expect(app(CartManager::class)->resolvePrice($product->id, $otherVariant->id))->toBe(150.0);
});

test('two overlapping product-wide campaigns deterministically resolve to the lowest price', function () {
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $saleA = FlashSale::factory()->create(['starts_at' => now()->subHours(2)]);
    $saleB = FlashSale::factory()->create(['starts_at' => now()->subHour()]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $saleA->id, 'product_id' => $product->id, 'sale_price' => 160]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $saleB->id, 'product_id' => $product->id, 'sale_price' => 140]);

    expect(app(CartManager::class)->resolvePrice($product->id, null))->toBe(140.0);
});

test('checkout charges the flash-sale price and persists it on the order item', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    $flashItem = FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($product, 2))->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->subtotal)->toBe(300.0);
    expect((float) $order->items->first()->price)->toBe(150.0);
    expect($order->items->first()->flash_sale_item_id)->toBe($flashItem->id);
});

test('a client-submitted price field is ignored — the server always charges its own resolved price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();

    $payload = checkoutPayload($product, 1);
    // Try to smuggle a client price into the item payload — not a field
    // PlaceOrderRequest validates, so it must be silently dropped, not
    // honored.
    $payload['items'][0]['price'] = 1;

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->items->first()->price)->toBe(200.0);
});

test('a cart price that goes stale between add-to-cart and checkout is corrected, not silently charged', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $cart = app(CartManager::class)->getOrCreateUserCart($user);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 200]);

    // A flash sale starts after the item was added to the cart.
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    // Checkout is submitted WITHOUT resending `items` (relying on the
    // already-persisted cart), so nothing would re-sync the price unless
    // checkout itself revalidates it.
    $first = $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Flash Pricing Test', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ]);

    $first->assertSessionHas('error');
    expect(Order::query()->count())->toBe(0);
    expect($cart->items()->first()->price)->toBe(150.0);

    // Retrying now succeeds at the corrected (lower) price — never silently
    // charged the old one, and never stuck unable to buy at all.
    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Flash Pricing Test', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->items->first()->price)->toBe(150.0);
});

test('a mobile-banking payment is confirmed at exactly the flash-sale amount quoted at initiation', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($product, 1, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    $pending = PendingCheckout::query()->firstOrFail();
    expect((float) $pending->checkout_data['subtotal'])->toBe(150.0);

    $sms = ReceivedSms::create([
        'sender' => '01700000000', 'provider' => 'bkash', 'content' => 'test sms', 'received_at' => now(),
        'parsed_transaction_id' => 'TXNFLASH1', 'parsed_amount' => $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->items->first()->price)->toBe(150.0);
    // The exact amount quoted at initiation, paid via SMS, and finally
    // persisted on the order/payment must all agree — none recomputed.
    expect((float) $order->total)->toBe((float) $pending->checkout_data['total']);
    $payment = Payment::where('order_id', $order->id)->firstOrFail();
    expect((float) $payment->amount)->toBe((float) $order->total);
});

test('a flash sale activated after mobile-banking initiation does not retroactively consume its cap for an order paid at the ordinary price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();

    // No flash sale exists yet — the customer is quoted and pays the
    // ordinary price.
    $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($product, 1, ['payment_method' => 'mobile_banking']))
        ->assertRedirect();

    $pending = PendingCheckout::query()->firstOrFail();
    expect((float) $pending->checkout_data['items'][0]['price'])->toBe(100.0);

    // A flash sale for the same product goes live before the customer
    // confirms payment.
    $flashSale = FlashSale::factory()->create();
    $flashItem = FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id, 'product_id' => $product->id,
        'sale_price' => 70, 'stock_limit' => 5, 'sold_count' => 0,
    ]);

    $sms = ReceivedSms::create([
        'sender' => '01700000000', 'provider' => 'bkash', 'content' => 'test sms', 'received_at' => now(),
        'parsed_transaction_id' => 'TXNRACE1', 'parsed_amount' => $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect((float) $order->items->first()->price)->toBe(100.0);
    expect($order->items->first()->flash_sale_item_id)->toBeNull();
    expect($flashItem->refresh()->sold_count)->toBe(0);
});

test('a flash-sale discount and a coupon stack without rounding or total drift', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'sale_price' => 150]);

    Coupon::create([
        'code' => 'SAVE10',
        'name' => 'Save 10%',
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'used_count' => 0,
    ]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), checkoutPayload($product, 2, ['coupon_code' => 'SAVE10']))
        ->assertRedirect();

    $order = Order::query()->firstOrFail();
    // Subtotal uses the flash price (150 x 2 = 300); coupon then takes 10% off that.
    expect((float) $order->subtotal)->toBe(300.0);
    expect((float) $order->discount)->toBe(30.0);
});
