<?php

declare(strict_types=1);

use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;

function enableGuestCheckoutForTracking(): void
{
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
}

test('a valid phone number with items is tracked as an incomplete order', function () {
    enableGuestCheckoutForTracking();
    $product = Product::factory()->create(['price' => 250]);

    $response = $this->post(route('checkout.track-incomplete'), [
        'attempt_token' => 'attempt-1',
        'phone' => '01712345678',
        'name' => 'Jane Buyer',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'tracked']);

    $incomplete = IncompleteOrder::query()->firstOrFail();
    expect($incomplete->attempt_token)->toBe('attempt-1');
    expect($incomplete->phone)->toBe('01712345678');
    expect($incomplete->name)->toBe('Jane Buyer');
    expect($incomplete->subtotal)->toBe(500.0);
    expect($incomplete->items)->toHaveCount(1);
    expect($incomplete->items[0]['product_id'])->toBe($product->id);
    expect($incomplete->items[0]['quantity'])->toBe(2);
});

test('tracking the same attempt token again updates the existing row instead of creating a new one', function () {
    enableGuestCheckoutForTracking();
    $product = Product::factory()->create(['price' => 100]);

    $payload = [
        'attempt_token' => 'attempt-2',
        'phone' => '01712345678',
        'name' => null,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ];

    $this->post(route('checkout.track-incomplete'), $payload)->assertOk();

    $payload['items'][0]['quantity'] = 3;
    $payload['name'] = 'Changed Name';
    $this->post(route('checkout.track-incomplete'), $payload)->assertOk();

    expect(IncompleteOrder::count())->toBe(1);
    $incomplete = IncompleteOrder::query()->firstOrFail();
    expect($incomplete->name)->toBe('Changed Name');
    expect($incomplete->items[0]['quantity'])->toBe(3);
});

test('a different attempt token for the same phone creates a separate row', function () {
    enableGuestCheckoutForTracking();
    $product = Product::factory()->create();

    $payload = [
        'phone' => '01712345678',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ];

    $this->post(route('checkout.track-incomplete'), [...$payload, 'attempt_token' => 'attempt-a'])->assertOk();
    $this->post(route('checkout.track-incomplete'), [...$payload, 'attempt_token' => 'attempt-b'])->assertOk();

    expect(IncompleteOrder::count())->toBe(2);
});

test('an invalid phone number is rejected and nothing is tracked', function () {
    enableGuestCheckoutForTracking();
    $product = Product::factory()->create();

    $response = $this->post(route('checkout.track-incomplete'), [
        'attempt_token' => 'attempt-3',
        'phone' => '12345',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);

    $response->assertInvalid(['phone']);
    expect(IncompleteOrder::count())->toBe(0);
});

test('placing a real COD order deletes any incomplete order rows tracked against the same phone', function () {
    enableGuestCheckoutForTracking();
    $product = Product::factory()->create(['price' => 299, 'stock_quantity' => 10]);

    IncompleteOrder::factory()->create(['phone' => '01712345678']);
    IncompleteOrder::factory()->create(['phone' => '01712345678']);
    IncompleteOrder::factory()->create(['phone' => '01800000000']);

    $response = $this->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'phone' => '01712345678',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ]);

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number, false));

    expect(IncompleteOrder::where('phone', '01712345678')->count())->toBe(0);
    expect(IncompleteOrder::where('phone', '01800000000')->count())->toBe(1);
});
