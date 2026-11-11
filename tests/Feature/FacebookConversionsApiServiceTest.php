<?php

declare(strict_types=1);

use App\Exceptions\MetaConversionsApiException;
use App\Models\MetaTrackingEvent;
use App\Models\Order;
use App\Models\Setting;
use App\Services\FacebookConversionsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
 * Queue::fake() must be active before this runs: Order::booted() records the
 * Purchase and queues SendMetaTrackingEventJob itself whenever the
 * integration is already configured, and these tests call the service
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

/**
 * The Purchase outbox row Order::booted() records, or a stand-in when the
 * integration was off at order time (the "not configured" tests).
 */
function purchaseEventFor(Order $order): MetaTrackingEvent
{
    return MetaTrackingEvent::query()->where('order_id', $order->id)->first()
        ?? MetaTrackingEvent::create([
            'event_name' => 'Purchase',
            'event_id' => 'order-'.$order->order_number,
            'order_id' => $order->id,
            'custom_data' => [],
            'client_ip_address' => $order->ip_address,
            'client_user_agent' => $order->user_agent,
            'event_time' => now(),
        ]);
}

test('sends a Purchase event with hashed email/phone when configured', function () {
    configureFacebookConversionsApi();
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

    $order = makeOrderWithItem();
    $purchase = purchaseEventFor($order);
    $purchase->forceFill(['client_ip_address' => '203.0.113.5', 'client_user_agent' => 'PestTestAgent/1.0'])->save();

    (new FacebookConversionsApiService)->send($purchase);

    Http::assertSent(function ($request) use ($order) {
        $data = $request->data();
        $event = $data['data'][0];

        return str_contains($request->url(), '/v26.0/123456789012345/events')
            && $data['access_token'] === 'test-access-token'
            && $event['event_name'] === 'Purchase'
            && $event['event_id'] === "order-{$order->order_number}"
            && $event['action_source'] === 'website'
            && $event['custom_data']['value'] == 560
            && $event['custom_data']['currency'] === 'BDT'
            && $event['custom_data']['order_id'] === $order->order_number
            && $event['custom_data']['num_items'] === 1
            && $event['custom_data']['contents'] == [['id' => 'SKU-1', 'quantity' => 1, 'item_price' => 500]]
            && $event['user_data']['em'][0] === hash('sha256', 'customer@example.com')
            && $event['user_data']['ph'][0] === hash('sha256', '8801712345678')
            && $event['user_data']['fn'][0] === hash('sha256', 'test')
            && $event['user_data']['ln'][0] === hash('sha256', 'customer')
            && $event['user_data']['country'][0] === hash('sha256', 'bd')
            && $event['user_data']['client_ip_address'] === '203.0.113.5'
            && $event['user_data']['client_user_agent'] === 'PestTestAgent/1.0'
            && ! isset($data['test_event_code']);
    });
});

test('includes the test event code when one is configured', function () {
    configureFacebookConversionsApi(testEventCode: 'TEST987');
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

    (new FacebookConversionsApiService)->send(purchaseEventFor(makeOrderWithItem()));

    Http::assertSent(fn ($request) => ($request->data())['test_event_code'] === 'TEST987');
});

test('sends nothing when the integration is not fully configured', function () {
    Queue::fake();
    Http::fake();

    expect(fn () => (new FacebookConversionsApiService)->send(purchaseEventFor(makeOrderWithItem())))
        ->toThrow(MetaConversionsApiException::class);

    Http::assertNothingSent();
});

test('sends nothing when the toggle is off even with a pixel id and token saved', function () {
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-access-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => false, 'group' => 'marketing']);
    Queue::fake();
    Http::fake();

    expect(fn () => (new FacebookConversionsApiService)->send(purchaseEventFor(makeOrderWithItem())))
        ->toThrow(MetaConversionsApiException::class);

    Http::assertNothingSent();
});

test('a permanent rejection is reported as not retryable, with a sanitized message', function () {
    configureFacebookConversionsApi();
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100, 'error_subcode' => 2804003]], 400)]);

    try {
        (new FacebookConversionsApiService)->send(purchaseEventFor(makeOrderWithItem()));
        $this->fail('Expected a MetaConversionsApiException.');
    } catch (MetaConversionsApiException $e) {
        expect($e->retryable)->toBeFalse()
            ->and($e->responseStatus)->toBe(400)
            ->and($e->getMessage())->toBe('HTTP 400 code 100/2804003: Invalid parameter')
            ->and($e->getMessage())->not->toContain('test-access-token');
    }

    Http::assertSentCount(1);
});

test('a timeout, rate limit, server error or transient Meta error is retryable', function (Closure $fake) {
    configureFacebookConversionsApi();
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => $fake]);

    try {
        (new FacebookConversionsApiService)->send(purchaseEventFor(makeOrderWithItem()));
        $this->fail('Expected a MetaConversionsApiException.');
    } catch (MetaConversionsApiException $e) {
        expect($e->retryable)->toBeTrue();
    }
})->with([
    'timeout' => fn () => fn () => throw new ConnectionException('timed out'),
    'rate limited' => fn () => fn () => Http::response([], 429),
    'server error' => fn () => fn () => Http::response([], 503),
    'transient' => fn () => fn () => Http::response(['error' => ['message' => 'Temporary', 'is_transient' => true]], 400),
]);
