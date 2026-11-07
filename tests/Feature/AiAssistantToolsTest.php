<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AiAssistantTools;

function aiAssistantTestOrder(array $overrides = []): Order
{
    $address = ['name' => 'Test Customer', 'phone' => '01712345678', 'email' => 'buyer@example.com', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    return Order::create(array_merge([
        'order_number' => 'ORD-AI-'.uniqid(), 'status' => 'processing', 'payment_status' => 'paid',
        'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => $address, 'billing_address' => $address,
    ], $overrides));
}

test('a guest can look up their own order with the right phone number', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, '01712345678');

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($order->order_number);
});

test('a guest cannot look up an order with the wrong phone number', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, '01999999999');

    expect($result['found'])->toBeFalse()
        ->and($result)->not->toHaveKey('order_number')
        ->and($result)->not->toHaveKey('total');
});

test('a guest cannot look up an order without any contact info at all', function () {
    $order = aiAssistantTestOrder();

    $result = app(AiAssistantTools::class)->lookupOrder(null, $order->order_number, null, null);

    expect($result['found'])->toBeFalse();
});

test('a logged-in customer can reach their own order without any phone or email', function () {
    $user = User::factory()->create();
    $order = aiAssistantTestOrder(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, $order->order_number, null, null);

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($order->order_number);
});

test('a logged-in customer can never reach another customer\'s order by number', function () {
    $user = User::factory()->create();
    $otherUsersOrder = aiAssistantTestOrder(['user_id' => User::factory()->create()->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, $otherUsersOrder->order_number, null, null);

    expect($result['found'])->toBeFalse()
        ->and($result)->not->toHaveKey('total');
});

test('a logged-in customer with no order number gets their latest order', function () {
    $user = User::factory()->create();
    $older = aiAssistantTestOrder(['user_id' => $user->id]);
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $latest = aiAssistantTestOrder(['user_id' => $user->id]);

    $result = app(AiAssistantTools::class)->lookupOrder($user, null, null, null);

    expect($result['found'])->toBeTrue()
        ->and($result['order_number'])->toBe($latest->order_number);
});

test('searching products only returns publicly visible products', function () {
    $visible = Product::factory()->create(['name' => 'Blue Winter Jacket', 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->create(['name' => 'Blue Winter Scarf', 'is_active' => false, 'published_at' => now()->subDay()]);

    $result = app(AiAssistantTools::class)->searchProducts('Blue Winter');

    $names = collect($result['products'])->pluck('name')->all();

    expect($names)->toContain('Blue Winter Jacket')
        ->and($names)->not->toContain('Blue Winter Scarf');
});

test('checking an inactive product reports it as not found, not as out of stock', function () {
    Product::factory()->create(['name' => 'Discontinued Mug', 'is_active' => false, 'published_at' => now()->subDay()]);

    $result = app(AiAssistantTools::class)->checkProductAvailability('Discontinued Mug');

    expect($result['found'])->toBeFalse();
});
