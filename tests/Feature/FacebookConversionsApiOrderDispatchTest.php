<?php

declare(strict_types=1);

use App\Jobs\SendFacebookPurchaseEventJob;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Order::booted()'s static::created() is the single confirmed choke point
 * for every order-creation path (COD, mobile banking, landing page, admin
 * manual order, incomplete-order conversion) — one test here is enough,
 * no need to duplicate across all six controller call sites.
 */
function createTestOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-CAPI-DISPATCH',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [],
        'billing_address' => [],
    ]);
}

test('a Facebook purchase event job is queued when the integration is configured', function () {
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => true, 'group' => 'marketing']);
    Queue::fake();

    $order = createTestOrder();

    Queue::assertPushed(SendFacebookPurchaseEventJob::class, fn ($job) => $job->orderId === $order->id);
});

test('nothing is queued when the integration is not configured', function () {
    Queue::fake();

    createTestOrder();

    Queue::assertNotPushed(SendFacebookPurchaseEventJob::class);
});
