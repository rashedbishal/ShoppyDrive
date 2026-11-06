<?php

declare(strict_types=1);

use App\Console\Commands\SyncSteadfastOrderStatuses;
use App\Models\CourierDeliveryEvidence;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::insert([
        ['key' => 'steadfast_api_key', 'value' => json_encode('api-key'), 'group' => 'courier'],
        ['key' => 'steadfast_secret_key', 'value' => json_encode('secret-key'), 'group' => 'courier'],
    ]);
});

function steadfastSyncOrder(string $orderNumber, string $status, ?string $consignmentId): Order
{
    return Order::create([
        'order_number' => $orderNumber,
        'status' => $status,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [],
        'billing_address' => [],
        'steadfast_consignment_id' => $consignmentId,
    ]);
}

function fakeSteadfastStatus(string $status): void
{
    Http::fake(['*/status_by_cid/*' => Http::response(['status' => 200, 'delivery_status' => $status])]);
}

test('a parcel Steadfast reports as delivered moves the order, and the proof is recorded as a poll', function () {
    $order = steadfastSyncOrder('ORD-SF-1', 'shipped', 'CID-1');
    fakeSteadfastStatus('delivered_parcels');

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    expect($order->fresh()->status->value)->toBe('delivered')
        ->and($order->fresh()->steadfast_status)->toBe('delivered_parcels')
        ->and(CourierDeliveryEvidence::query()->where('order_id', $order->id)->where('source', 'steadfast_poll')->exists())->toBeTrue();
});

test('a status the app does not recognise is recorded but never moves the order', function (string $status) {
    $order = steadfastSyncOrder('ORD-SF-2', 'shipped', 'CID-2');
    fakeSteadfastStatus($status);

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    expect($order->fresh()->status->value)->toBe('shipped')
        ->and($order->fresh()->steadfast_status)->toBe($status);
})->with(['hold', 'unknown', 'some_new_status']);

test('polling the same status again adds no duplicate history', function () {
    $order = steadfastSyncOrder('ORD-SF-3', 'shipped', 'CID-3');
    fakeSteadfastStatus('hold');

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();
    $historyAfterFirstRun = $order->statusHistories()->count();
    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    expect($order->statusHistories()->count())->toBe($historyAfterFirstRun);
});

test('finished, unsent, other-courier and very old orders are not polled', function () {
    steadfastSyncOrder('ORD-SF-4', 'delivered', 'CID-4');
    steadfastSyncOrder('ORD-SF-5', 'cancelled', 'CID-5');
    steadfastSyncOrder('ORD-SF-6', 'refunded', 'CID-6');
    steadfastSyncOrder('ORD-SF-7', 'shipped', null);
    $old = steadfastSyncOrder('ORD-SF-8', 'shipped', 'CID-8');
    $old->forceFill(['created_at' => now()->subDays(SyncSteadfastOrderStatuses::MAX_ORDER_AGE_DAYS + 1)])->save();
    fakeSteadfastStatus('delivered_parcels');

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    Http::assertNothingSent();
});

test('one failed check does not stop the other orders', function () {
    $first = steadfastSyncOrder('ORD-SF-9', 'shipped', 'CID-BAD');
    $second = steadfastSyncOrder('ORD-SF-10', 'shipped', 'CID-GOOD');
    Http::fake([
        '*/status_by_cid/CID-BAD' => Http::response(['status' => 500, 'message' => 'Server error'], 500),
        '*/status_by_cid/CID-GOOD' => Http::response(['status' => 200, 'delivery_status' => 'delivered_parcels']),
    ]);

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    expect($first->fresh()->status->value)->toBe('shipped')
        ->and($second->fresh()->status->value)->toBe('delivered')
        ->and(SyncSteadfastOrderStatuses::currentProblem())->toBeNull();
});

test('when every check fails the run stops early and the dashboard shows a banner', function () {
    foreach (range(1, 5) as $number) {
        steadfastSyncOrder("ORD-SF-OUT-{$number}", 'shipped', "CID-OUT-{$number}");
    }
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection timed out');
    });

    $this->artisan('steadfast:sync-statuses')->assertFailed();

    expect($attempts)->toBe(SyncSteadfastOrderStatuses::MAX_CONSECUTIVE_FAILURES)
        ->and(SyncSteadfastOrderStatuses::currentProblem()['last_error'])->toContain('timed out');

    actingAs(createAdmin(), 'admin');
    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.steadfast_sync_problem.last_error', fn (string $error): bool => str_contains($error, 'timed out')));
});

test('a later successful run clears the banner', function () {
    Cache::forever(SyncSteadfastOrderStatuses::PROBLEM_CACHE_KEY, ['since' => now()->getTimestamp(), 'last_failed_at' => now()->getTimestamp(), 'last_error' => 'old']);
    steadfastSyncOrder('ORD-SF-11', 'shipped', 'CID-11');
    fakeSteadfastStatus('hold');

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    expect(SyncSteadfastOrderStatuses::currentProblem())->toBeNull();
});

test('no orders to check is a healthy run', function () {
    Http::fake();

    $this->artisan('steadfast:sync-statuses')->assertSuccessful();

    Http::assertNothingSent();
});
