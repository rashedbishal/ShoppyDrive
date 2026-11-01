<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\PendingCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function pruneTestOrder(string $orderNumber): Order
{
    return Order::create([
        'order_number' => $orderNumber,
        'status' => 'processing',
        'payment_status' => 'paid',
        'payment_method' => 'zinipay',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function pruneTestPending(array $overrides = []): PendingCheckout
{
    return PendingCheckout::create(array_merge([
        'token' => (string) Str::uuid(),
        'checkout_data' => ['order_number' => 'ORD-X', 'total' => 560],
        'expires_at' => now()->subHours(1),
    ], $overrides));
}

test('a converted record older than 24 hours is pruned', function () {
    pruneTestOrder('ORD-OLD-1');
    $pending = pruneTestPending(['order_number' => 'ORD-OLD-1', 'expires_at' => now()->subHours(25)]);

    test()->artisan('pending-checkouts:prune')->assertSuccessful();

    expect(PendingCheckout::find($pending->id))->toBeNull();
});

test('a converted record newer than 24 hours is preserved', function () {
    pruneTestOrder('ORD-NEW-1');
    $pending = pruneTestPending(['order_number' => 'ORD-NEW-1', 'expires_at' => now()->subHours(1)]);

    test()->artisan('pending-checkouts:prune')->assertSuccessful();

    expect(PendingCheckout::find($pending->id))->not->toBeNull();
});

test('a record with no order_number is always preserved, no matter how old', function () {
    $pending = pruneTestPending(['order_number' => null, 'expires_at' => now()->subDays(10)]);

    test()->artisan('pending-checkouts:prune')->assertSuccessful();

    expect(PendingCheckout::find($pending->id))->not->toBeNull();
});

test('a converted record whose Order no longer exists is preserved and reported', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Preserved')
            && $context['order_number'] === 'ORD-MISSING-1');

    $pending = pruneTestPending(['order_number' => 'ORD-MISSING-1', 'expires_at' => now()->subHours(25)]);

    test()->artisan('pending-checkouts:prune')->assertSuccessful();

    expect(PendingCheckout::find($pending->id))->not->toBeNull();
});

test('the command is safe to run repeatedly', function () {
    pruneTestOrder('ORD-REPEAT-1');
    $pending = pruneTestPending(['order_number' => 'ORD-REPEAT-1', 'expires_at' => now()->subHours(25)]);

    test()->artisan('pending-checkouts:prune')->assertSuccessful();
    expect(PendingCheckout::find($pending->id))->toBeNull();

    // Nothing left to delete this time — must not error on an empty result.
    test()->artisan('pending-checkouts:prune')->assertSuccessful();
});
