<?php

declare(strict_types=1);

use App\Console\Commands\SyncPathaoOrderStatuses;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CourierVerificationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
        ['key' => 'pathao_store_id', 'value' => json_encode('555'), 'group' => 'courier'],
        ['key' => 'pathao_token', 'value' => json_encode([
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String(),
        ]), 'group' => 'courier'],
    ]);
});

function makeCommandTestOrder(string $orderNumber, string $status, ?string $consignmentId): Order
{
    $product = Product::factory()->create();

    $order = Order::create([
        'order_number' => $orderNumber,
        'status' => $status,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        'pathao_consignment_id' => $consignmentId,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    return $order;
}

it('syncs status for active pathao orders and skips terminal/unsent ones', function () {
    $active = makeCommandTestOrder('ORD-ACTIVE', 'processing', 'CONSIGN-ACTIVE');
    makeCommandTestOrder('ORD-DELIVERED', 'delivered', 'CONSIGN-DELIVERED');
    makeCommandTestOrder('ORD-NO-CONSIGNMENT', 'pending', null);

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-ACTIVE/info' => Http::response([
            'type' => 'success',
            'data' => ['order_status_slug' => 'in_transit'],
        ]),
    ]);

    $this->artisan('pathao:sync-statuses')->assertSuccessful();

    expect($active->refresh()->status->value)->toBe('shipped');

    Http::assertSentCount(1);
});

it('a connection failure on one order does not abort syncing the rest of the batch', function () {
    $unreachable = makeCommandTestOrder('ORD-UNREACHABLE', 'processing', 'CONSIGN-UNREACHABLE');
    $reachable = makeCommandTestOrder('ORD-REACHABLE', 'processing', 'CONSIGN-REACHABLE');

    Http::fake([
        '*/aladdin/api/v1/orders/CONSIGN-UNREACHABLE/info' => fn () => throw new ConnectionException('Connection timed out'),
        '*/aladdin/api/v1/orders/CONSIGN-REACHABLE/info' => Http::response([
            'type' => 'success',
            'data' => ['order_status_slug' => 'in_transit'],
        ]),
    ]);

    $this->artisan('pathao:sync-statuses')
        ->expectsOutputToContain('Order ORD-UNREACHABLE: Connection timed out')
        ->assertSuccessful();

    expect($unreachable->refresh()->status->value)->toBe('processing');
    expect($reachable->refresh()->status->value)->toBe('shipped');
});

/*
 * BE-011: a run where every Pathao check failed used to report success,
 * hammer Pathao once per order, and record a healthy scheduler marker.
 */
function pathaoInfoFailsForEveryOrder(): void
{
    Http::fake(['*/aladdin/api/v1/orders/*/info' => Http::response(['message' => 'Unauthenticated.'], 401)]);
}

it('fails the run, flags the problem and records no scheduler marker when every check fails', function () {
    makeCommandTestOrder('ORD-FAIL-1', 'processing', 'CONSIGN-FAIL-1');
    makeCommandTestOrder('ORD-FAIL-2', 'processing', 'CONSIGN-FAIL-2');
    pathaoInfoFailsForEveryOrder();

    $this->artisan('pathao:sync-statuses')->assertFailed();

    expect(SyncPathaoOrderStatuses::currentProblem())
        ->toMatchArray(['last_error' => 'Unauthenticated.'])
        ->and(Cache::get(CourierVerificationReadinessService::SCHEDULER_MARKER_KEY))->toBeNull();
});

it('stops after three failures in a row instead of asking Pathao about every order', function () {
    foreach (range(1, 6) as $n) {
        makeCommandTestOrder("ORD-STOP-{$n}", 'processing', "CONSIGN-STOP-{$n}");
    }
    pathaoInfoFailsForEveryOrder();

    $this->artisan('pathao:sync-statuses')->assertFailed();

    Http::assertSentCount(3);
});

it('keeps the first failure time across failing runs and clears the problem once a check succeeds', function () {
    makeCommandTestOrder('ORD-RECOVER', 'processing', 'CONSIGN-RECOVER');
    // Http::fake() adds to earlier fakes rather than replacing them, so one
    // fake switched by this flag stands in for Pathao going down and back up.
    $pathaoUp = false;
    Http::fake(['*/aladdin/api/v1/orders/*/info' => function () use (&$pathaoUp) {
        return $pathaoUp
            ? Http::response(['type' => 'success', 'data' => ['order_status_slug' => 'in_transit']])
            : Http::response(['message' => 'Unauthenticated.'], 401);
    }]);

    $this->travelTo(now()->subHour());
    $this->artisan('pathao:sync-statuses')->assertFailed();
    $since = SyncPathaoOrderStatuses::currentProblem()['since'];
    $this->travelBack();
    $this->artisan('pathao:sync-statuses')->assertFailed();

    expect(SyncPathaoOrderStatuses::currentProblem()['since'])->toBe($since);

    $pathaoUp = true;
    $this->artisan('pathao:sync-statuses')->assertSuccessful();

    expect(SyncPathaoOrderStatuses::currentProblem())->toBeNull()
        ->and(Cache::get(CourierVerificationReadinessService::SCHEDULER_MARKER_KEY))->not->toBeNull();
});

it('a run with nothing to check is still a healthy run', function () {
    Http::fake();

    $this->artisan('pathao:sync-statuses')->assertSuccessful();

    expect(Cache::get(CourierVerificationReadinessService::SCHEDULER_MARKER_KEY))->not->toBeNull();
    Http::assertNothingSent();
});
