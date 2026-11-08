<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Setting;
use App\Services\FacebookConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function configureFacebookConversionsApi(?string $testEventCode = null): void
{
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-access-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => true, 'group' => 'marketing']);

    if ($testEventCode !== null) {
        Setting::create(['key' => 'facebook_conversions_api_test_event_code', 'value' => $testEventCode, 'group' => 'marketing']);
    }
}

/**
 * Queue::fake() must be active before this runs: Order::booted() dispatches
 * SendFacebookPurchaseEventJob itself on creation whenever the integration
 * is already configured, and these tests call FacebookConversionsApiService
 * directly — without the fake, that's a second, real HTTP call racing this
 * file's own assertions on the sync queue connection.
 */
function makeOrderWithItem(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-CAPI-TEST',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [
            'name' => 'Test Customer',
            'email' => 'Customer@Example.com',
            'phone' => '01712345678',
        ],
        'billing_address' => [],
    ], $overrides));

    // ip_address/user_agent are deliberately excluded from $fillable (see
    // Order::booted()) so they can't be spoofed via create() — set them the
    // same way the model's own docblock says tests should: forceFill().
    $order->forceFill([
        'ip_address' => '203.0.113.5',
        'user_agent' => 'PestTestAgent/1.0',
    ])->save();

    $order->items()->create([
        'product_name' => 'Test Product',
        'sku' => 'SKU-1',
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => [],
    ]);

    return $order->fresh(['items']);
}

test('sends a Purchase event with hashed email/phone when configured', function () {
    configureFacebookConversionsApi();
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

    $order = makeOrderWithItem();

    (new FacebookConversionsApiService)->sendPurchaseEvent($order);

    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();
        $event = $data['data'][0];

        return str_contains($request->url(), '/123456789012345/events')
            && $data['access_token'] === 'test-access-token'
            && $event['event_name'] === 'Purchase'
            && $event['event_id'] === "order-{$order->order_number}"
            && $event['custom_data']['value'] === 560.0
            && $event['custom_data']['currency'] === 'BDT'
            && $event['user_data']['em'][0] === hash('sha256', 'customer@example.com')
            && $event['user_data']['ph'][0] === hash('sha256', '8801712345678')
            && $event['user_data']['client_ip_address'] === '203.0.113.5'
            && ! isset($data['test_event_code']);
    });
});

test('includes the test event code when one is configured', function () {
    configureFacebookConversionsApi(testEventCode: 'TEST987');
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

    (new FacebookConversionsApiService)->sendPurchaseEvent(makeOrderWithItem());

    Http::assertSent(fn ($request) => ($request->data())['test_event_code'] === 'TEST987');
});

test('sends nothing when the integration is not fully configured', function () {
    Queue::fake();
    Http::fake();

    (new FacebookConversionsApiService)->sendPurchaseEvent(makeOrderWithItem());

    Http::assertNothingSent();
});

test('sends nothing when the toggle is off even with a pixel id and token saved', function () {
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-access-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => false, 'group' => 'marketing']);
    Queue::fake();
    Http::fake();

    (new FacebookConversionsApiService)->sendPurchaseEvent(makeOrderWithItem());

    Http::assertNothingSent();
});

test('a failed Graph API call never throws', function () {
    configureFacebookConversionsApi();
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'bad request'], 400)]);

    (new FacebookConversionsApiService)->sendPurchaseEvent(makeOrderWithItem());

    Http::assertSentCount(1);
});
