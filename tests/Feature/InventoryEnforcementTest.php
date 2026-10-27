<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Cart;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReceivedSms;
use App\Models\User;
use App\Services\InventoryService;

use function Pest\Laravel\actingAs;

test('COD checkout decrements product stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Stock Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3],
        ],
    ]);

    $response->assertRedirect();
    expect($product->refresh()->stock_quantity)->toBe(2);

    expect(InventoryLog::where('product_id', $product->id)->where('type', 'out')->first())
        ->not->toBeNull();
});

test('checkout is rejected when a product that has variants is ordered without picking one', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 999]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Stock Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            // No variant_id, even though the product has one — must not
            // fall through to decrementing the denormalized sum column.
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(999);
});

test('COD checkout decrements variant stock independently of product stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 999]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100, 'stock_quantity' => 4]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Stock Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 4],
        ],
    ])->assertRedirect();

    expect($variant->refresh()->stock_quantity)->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(999);
});

test('checkout is rejected when requested quantity exceeds available stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 2]);

    Cart::query()->where('user_id', $user->id)->delete();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Stock Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5],
        ],
    ]);

    $response->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(2);
});

test('a second concurrent-style checkout for the last unit is rejected cleanly', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 1]);

    $payload = fn (User $user) => [
        'name' => 'Stock Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ];

    $this->actingAs($userA)->post(route('checkout.store'), $payload($userA))->assertRedirect();
    $second = $this->actingAs($userB)->post(route('checkout.store'), $payload($userB));

    $second->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(1);
    expect($product->refresh()->stock_quantity)->toBe(0);
});

test('the flash-sale cap limits the discount, not the product: it is tracked independently of ordinary stock and a purchase after exhaustion falls back to the regular price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50]);
    $flashSale = FlashSale::factory()->create();
    $flashItem = FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 80,
        'stock_limit' => 2,
        'sold_count' => 0,
    ]);

    Cart::query()->where('user_id', $user->id)->delete();

    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Flash Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ])->assertRedirect();

    // Both units bought at the flash price, cap now exhausted; the flash
    // sub-cap and the product's ordinary stock are tracked separately.
    expect($flashItem->refresh()->sold_count)->toBe(2);
    expect($product->refresh()->stock_quantity)->toBe(48);
    $firstOrder = Order::query()->firstOrFail();
    expect((float) $firstOrder->items->first()->price)->toBe(80.0);

    // Plenty of ordinary stock remains, but the flash allocation is gone —
    // this purchase must succeed at the regular price, not be rejected.
    $response = $this->actingAs(User::factory()->create())->post(route('checkout.store'), [
        'name' => 'Flash Test 2',
        'email' => 'second@example.com',
        'phone' => '01700000001',
        'address_line1' => 'House 2',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);

    $response->assertRedirect();
    expect($flashItem->refresh()->sold_count)->toBe(2);
    expect($product->refresh()->stock_quantity)->toBe(47);
    $secondOrder = Order::query()->where('id', '!=', $firstOrder->id)->firstOrFail();
    expect((float) $secondOrder->items->first()->price)->toBe(100.0);
    expect($secondOrder->items->first()->flash_sale_item_id)->toBeNull();
});

test('admin cancelling an order restores the stock it consumed', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Cancel Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3],
        ],
    ])->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(7);

    $order = Order::query()->firstOrFail();

    auth('web')->logout();
    actingAs($admin, 'admin');
    $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Customer request'])
        ->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(10);
    expect($order->refresh()->stock_restored_at)->not->toBeNull();

    expect(InventoryLog::where('product_id', $product->id)->where('type', 'release')->first())
        ->not->toBeNull();
});

test('restoring stock for an order twice does not double-credit it', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);
    $order = Order::create([
        'order_number' => 'ORD-TEST-0001',
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 100,
        'shipping_address' => [],
        'billing_address' => [],
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'product_name' => $product->name,
        'price' => 100,
        'quantity' => 2,
        'subtotal' => 200,
        'product_snapshot' => [],
    ]);

    $service = app(InventoryService::class);
    $service->restoreForOrder($order->fresh());
    $service->restoreForOrder($order->fresh());

    expect($product->refresh()->stock_quantity)->toBe(12);
});

test('confirming a mobile-banking payment decrements stock and rejects if stock ran out meanwhile', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 2]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Mobile Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ])->assertRedirect();

    $pending = PendingCheckout::query()->firstOrFail();

    $sms = ReceivedSms::create([
        'sender' => '01700000000',
        'provider' => 'bkash',
        'content' => 'test sms',
        'received_at' => now(),
        'parsed_transaction_id' => 'TXN999999',
        'parsed_amount' => $pending->checkout_data['total'],
        'is_matched' => false,
    ]);

    // Stock is depleted by something else between initiating payment and confirming it.
    $product->update(['stock_quantity' => 0]);

    $response = $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash',
        'transaction_id' => $sms->parsed_transaction_id,
    ]);

    $response->assertSessionHasErrors('transaction_id');
    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();

    $product->update(['stock_quantity' => 2]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash',
        'transaction_id' => $sms->parsed_transaction_id,
    ])->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(0);
    expect(Order::query()->count())->toBe(1);
});

test('marking an order refunded restores its stock', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin-refund@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    Cart::query()->where('user_id', $user->id)->delete();
    $this->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Refund Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 4],
        ],
    ])->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(6);
    $order = Order::query()->firstOrFail();

    auth('web')->logout();
    actingAs($admin, 'admin');
    $this->patch(route('admin.orders.markRefunded', $order))->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(10);
});
