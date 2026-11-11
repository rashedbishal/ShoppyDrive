<?php

declare(strict_types=1);

use App\Jobs\SendFacebookPurchaseEventJob;
use App\Jobs\SendMetaTrackingEventJob;
use App\Models\MetaTrackingEvent;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

function enableFacebookConversionsApi(): void
{
    Setting::create(['key' => 'facebook_pixel_id', 'value' => '123456789012345', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'test-token', 'group' => 'marketing']);
    Setting::create(['key' => 'facebook_conversions_api_enabled', 'value' => true, 'group' => 'marketing']);
}

test('a placed order records one Purchase with the order event id and queues its delivery', function () {
    enableFacebookConversionsApi();
    Queue::fake();

    $order = createTestOrder();

    $purchase = MetaTrackingEvent::sole();
    expect($purchase->event_name)->toBe('Purchase')
        ->and($purchase->event_id)->toBe('order-ORD-CAPI-DISPATCH')
        ->and($purchase->order_id)->toBe($order->id)
        ->and($purchase->action_source)->toBe('website')
        ->and($purchase->event_source_url)->toBe(route('order.confirmation', 'ORD-CAPI-DISPATCH'));

    Queue::assertPushed(SendMetaTrackingEventJob::class, fn ($job) => $job->metaTrackingEventId === $purchase->id);
});

test('nothing is recorded or queued when the integration is not configured', function () {
    Queue::fake();

    createTestOrder();

    expect(MetaTrackingEvent::count())->toBe(0);
    Queue::assertNotPushed(SendMetaTrackingEventJob::class);
});

test('an order that rolls back leaves no Purchase behind', function () {
    enableFacebookConversionsApi();
    Queue::fake();

    try {
        DB::transaction(function (): void {
            createTestOrder();

            throw new RuntimeException('stock ran out');
        });
    } catch (RuntimeException) {
    }

    expect(MetaTrackingEvent::count())->toBe(0);

    // Even a job queued for the rolled-back row finds nothing to send.
    Http::fake();
    Queue::pushed(SendMetaTrackingEventJob::class)->each(fn ($job) => app()->call([$job, 'handle']));
    Http::assertNothingSent();
});

test('the Pixel cookies of the customer placing the order are kept with the Purchase', function () {
    enableFacebookConversionsApi();
    Queue::fake();
    app()->instance('request', Request::create('/checkout', 'POST', cookies: [
        '_fbp' => 'fb.1.1596403881668.1116446470',
        '_fbc' => 'fb.1.1596403881668.IwAR2xyz',
    ]));

    createTestOrder();

    expect(MetaTrackingEvent::sole())
        ->fbp->toBe('fb.1.1596403881668.1116446470')
        ->fbc->toBe('fb.1.1596403881668.IwAR2xyz');
});

test('an order created by staff in Admin is sent as a phone order without the staff member\'s IP, browser or cookies', function () {
    enableFacebookConversionsApi();
    Queue::fake();
    app()->instance('request', Request::create('/admin/orders', 'POST', cookies: ['_fbp' => 'fb.1.1596403881668.1116446470'], server: [
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_USER_AGENT' => 'StaffBrowser/1.0',
    ]));

    createTestOrder();

    expect(MetaTrackingEvent::sole())
        ->action_source->toBe('phone_call')
        ->event_source_url->toBeNull()
        ->client_ip_address->toBeNull()
        ->client_user_agent->toBeNull()
        ->fbp->toBeNull();
});

test('a leftover job of the old class records the Purchase once through the new outbox', function () {
    enableFacebookConversionsApi();
    Queue::fake();
    $order = createTestOrder();

    app()->call([new SendFacebookPurchaseEventJob($order->id), 'handle']);

    expect(MetaTrackingEvent::where('event_name', 'Purchase')->count())->toBe(1);
});
