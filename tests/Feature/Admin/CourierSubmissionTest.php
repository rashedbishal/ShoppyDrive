<?php

declare(strict_types=1);

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Exceptions\CourierSubmissionException;
use App\Models\Admin;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Services\CourierSubmissionService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

/**
 * COURIER-SEND-RACE-001 behavior on SQLite: claim rules, outcome
 * classification, the cancel/refund guards, reconciliation and the legacy
 * backfill. Real concurrency (two processes, real MySQL locking, a local
 * fake courier) is covered in tests/Feature/CourierSendConcurrencyRaceTest.php.
 */
function courierOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-COURIER-'.strtoupper(bin2hex(random_bytes(3))),
        'status' => OrderStatus::PROCESSING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Rafiq Islam', 'phone' => '01712345678', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => [],
    ], $overrides));
}

function steadfastCreated(string $consignmentId = 'SF-1'): array
{
    return ['status' => 200, 'consignment' => ['consignment_id' => $consignmentId, 'tracking_code' => "TRK-{$consignmentId}", 'status' => 'in_review']];
}

function connectionFailure(?int $errno, ?int $requestBytes): ConnectionException
{
    $context = $errno === null ? [] : ['errno' => $errno, 'request_size' => $requestBytes];

    return new ConnectionException('cURL error', 0, new ConnectException('cURL error', new PsrRequest('POST', 'https://courier.test'), null, $context));
}

function courierCreateRequestCount(): int
{
    return Http::recorded(fn ($request) => str_ends_with($request->url(), '/create_order') || str_ends_with($request->url(), '/aladdin/api/v1/orders'))->count();
}

beforeEach(function () {
    $this->admin = createAdmin();

    Setting::insert([
        ['key' => 'steadfast_api_key', 'value' => json_encode('api-key'), 'group' => 'courier'],
        ['key' => 'steadfast_secret_key', 'value' => json_encode('secret-key'), 'group' => 'courier'],
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_client_id', 'value' => json_encode('client-id'), 'group' => 'courier'],
        ['key' => 'pathao_client_secret', 'value' => json_encode('client-secret'), 'group' => 'courier'],
        ['key' => 'pathao_username', 'value' => json_encode('merchant@example.com'), 'group' => 'courier'],
        ['key' => 'pathao_password', 'value' => json_encode('secret'), 'group' => 'courier'],
        ['key' => 'pathao_store_id', 'value' => json_encode('555'), 'group' => 'courier'],
    ]);
});

function storePathaoToken(): void
{
    Setting::create(['key' => 'pathao_token', 'value' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()], 'group' => 'courier']);
}

describe('sending', function () {
    test('a successful send records a sent submission, releases the claim and blocks any further send', function () {
        $order = courierOrder();
        Http::fake(['*/create_order' => Http::response(steadfastCreated('SF-OK'))]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('success');

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::SENT)
            ->and($submission->active_order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe('SF-OK')
            ->and($submission->claimed_by_admin_id)->toBe($this->admin->id);
        expect($order->refresh()->steadfast_consignment_id)->toBe('SF-OK');

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('error', 'This order has already been sent to Steadfast.');
        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))->assertSessionHas('error', 'This order has already been sent to Steadfast.');

        expect(courierCreateRequestCount())->toBe(1);
        expect(CourierSubmission::count())->toBe(1);
    });

    test('a definite rejection releases the claim so the order can be sent again', function () {
        $order = courierOrder();
        Http::fake(['*/create_order' => Http::sequence()
            ->push(['status' => 422, 'message' => 'Invalid recipient phone'], 422)
            ->push(steadfastCreated('SF-RETRY'))]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('error', 'Steadfast: Invalid recipient phone');

        $failed = CourierSubmission::sole();
        expect($failed->state)->toBe(CourierSubmissionState::FAILED)
            ->and($failed->active_order_id)->toBeNull()
            ->and($failed->failure_kind)->toBe('rejected')
            ->and($failed->http_status)->toBe(422);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('success');

        expect($order->refresh()->steadfast_consignment_id)->toBe('SF-RETRY');
        expect(courierCreateRequestCount())->toBe(2);
    });

    test('an ambiguous courier response leaves the outcome unknown and blocks every further send without contacting a courier', function (Closure $response, string $failureKind) {
        $order = courierOrder();
        Http::fake(['*/create_order' => $response]);
        storePathaoToken();

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'did not confirm this booking'));
        // A faked request that throws isn't recorded, so compare against this.
        $createRequestsAfterFirstSend = courierCreateRequestCount();

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->active_order_id)->toBe($order->id)
            ->and($submission->failure_kind)->toBe($failureKind);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'in progress or its outcome is unresolved'));
        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'in progress or its outcome is unresolved'));

        expect(courierCreateRequestCount())->toBe($createRequestsAfterFirstSend);
        expect(CourierSubmission::count())->toBe(1);
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
    })->with([
        'HTTP 500' => [fn () => Http::response(['message' => 'Server error'], 500), 'server_error'],
        'HTTP 409' => [fn () => Http::response(['message' => 'Conflict'], 409), 'ambiguous_http_status'],
        'success without a consignment ID' => [fn () => Http::response(['status' => 200, 'consignment' => []]), 'missing_consignment_id'],
        'error payload inside a 200' => [fn () => Http::response(['status' => 400, 'message' => 'Odd']), 'error_payload'],
        'connection dropped with no transport evidence' => [fn () => throw new ConnectionException('Connection reset'), 'connection_interrupted'],
    ]);

    test('a failure before the create request is sent is a definite failure', function () {
        // No stored token: Pathao must first fetch one, and that request fails.
        $order = courierOrder();
        Http::fake([
            '*/aladdin/api/v1/issue-token' => fn () => throw new ConnectionException('Connection timed out'),
            '*/aladdin/api/v1/orders' => Http::response(['type' => 'success', 'data' => ['consignment_id' => 'PA-NEVER']]),
        ]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))->assertSessionHas('error');

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::FAILED)
            ->and($submission->failure_kind)->toBe('not_sent')
            ->and($submission->active_order_id)->toBeNull();
        expect(courierCreateRequestCount())->toBe(0);
    });

    test('an order that is cancelled, refunded or delivered cannot be sent', function (OrderStatus $status) {
        $order = courierOrder(['status' => $status]);
        Http::fake();

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))
            ->assertSessionHas('error', "A {$status->value} order can't be sent to a courier.");

        expect(CourierSubmission::count())->toBe(0);
        Http::assertNothingSent();
    })->with([OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::DELIVERED]);

    test('a send already in progress blocks another send', function () {
        $order = courierOrder();
        CourierSubmission::factory()->forOrder($order)->create();
        Http::fake();

        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'in progress or its outcome is unresolved'));

        Http::assertNothingSent();
        expect(CourierSubmission::count())->toBe(1);
    });

    test('a stale in-progress send becomes unknown and keeps blocking', function () {
        $order = courierOrder();
        $stale = CourierSubmission::factory()->forOrder($order)->stale()->create();
        Http::fake();

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('error');

        expect($stale->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($stale->failure_kind)->toBe('stale')
            ->and($stale->active_order_id)->toBe($order->id);
        Http::assertNothingSent();
    });

    test('the database refuses a second unresolved submission for the same order', function () {
        $order = courierOrder();
        CourierSubmission::factory()->forOrder($order)->create();

        expect(fn () => CourierSubmission::factory()->forOrder($order)->create(['courier' => 'pathao']))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('connection failure classification', function () {
    test('only failures that provably sent nothing release the claim', function (?int $errno, ?int $requestBytes, bool $outcomeUnknown) {
        $classified = CourierSubmissionException::fromConnectionException('Steadfast', connectionFailure($errno, $requestBytes));

        expect($classified->isOutcomeUnknown())->toBe($outcomeUnknown)
            ->and($classified->curlErrno)->toBe($errno);
    })->with([
        'could not resolve host' => [6, 0, false],
        'could not connect' => [7, 0, false],
        'timed out after sending' => [28, 412, true],
        'timed out with nothing sent' => [28, 0, true],
        'TLS handshake failed' => [35, 0, true],
        'empty reply' => [52, 412, true],
        'receive error' => [56, 412, true],
        'no transport evidence' => [null, null, true],
    ]);
});

describe('order actions while a submission is unresolved', function () {
    test('cancelling is refused without touching the order or the claim', function (Closure $makeSubmission) {
        $order = courierOrder();
        $submission = $makeSubmission($order);

        actingAs($this->admin, 'admin')->patch(route('admin.orders.cancel', $order), ['reason' => 'Customer asked'])
            ->assertSessionHas('error', "This order can't be cancelled while a courier submission is in progress or its outcome is unresolved. Resolve it in the Courier panel first.");

        expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING)
            ->and($order->cancellation_reason)->toBeNull();
        expect($submission->refresh()->active_order_id)->toBe($order->id);
        expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    })->with([
        'in progress' => [fn (Order $order) => CourierSubmission::factory()->forOrder($order)->create()],
        'unknown' => [fn (Order $order) => CourierSubmission::factory()->forOrder($order)->unknown()->create()],
    ]);

    test('marking refunded is refused without touching the order or the claim', function (Closure $makeSubmission) {
        $order = courierOrder();
        $submission = $makeSubmission($order);

        actingAs($this->admin, 'admin')->patch(route('admin.orders.markRefunded', $order), ['refund_amount' => 1, 'refund_method' => 'bKash'])
            ->assertSessionHas('error', "This order can't be marked refunded while a courier submission is in progress or its outcome is unresolved. Resolve it in the Courier panel first.");

        expect($order->refresh()->status)->toBe(OrderStatus::PROCESSING);
        expect($submission->refresh()->active_order_id)->toBe($order->id);
        expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(0);
    })->with([
        'in progress' => [fn (Order $order) => CourierSubmission::factory()->forOrder($order)->create()],
        'unknown' => [fn (Order $order) => CourierSubmission::factory()->forOrder($order)->unknown()->create()],
    ]);

    test('cancelling works again once the submission is resolved as not created', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmNotCreated', [$order, $submission]), [
            'note' => 'Searched the Steadfast portal by invoice, nothing found.',
            'confirmed' => true,
        ])->assertSessionHas('success');

        actingAs($this->admin, 'admin')->patch(route('admin.orders.cancel', $order))->assertSessionHas('success');

        expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    });
});

describe('reconciliation', function () {
    test('an admin can record a steadfast consignment verified in the portal', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        Http::fake(['*/status_by_cid/SF-FOUND' => Http::response(['status' => 200, 'delivery_status' => 'in_review'])]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), [
            'consignment_id' => 'SF-FOUND',
            'tracking_code' => 'TRK-FOUND',
            'note' => 'Found invoice in the Steadfast portal, booked 10:42.',
            'verified_in_portal' => true,
        ])->assertSessionHas('success');

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::SENT)
            ->and($submission->active_order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe('SF-FOUND')
            ->and($submission->resolution)->toBe('consignment_verified_in_portal')
            ->and($submission->resolved_by_admin_id)->toBe($this->admin->id)
            ->and($submission->resolved_at)->not->toBeNull()
            ->and($submission->resolution_note)->toBe('Found invoice in the Steadfast portal, booked 10:42.');

        $order->refresh();
        expect($order->steadfast_consignment_id)->toBe('SF-FOUND')
            ->and($order->steadfast_tracking_code)->toBe('TRK-FOUND');
        expect(OrderStatusHistory::where('order_id', $order->id)->pluck('notes')->implode(' '))->toContain('SF-FOUND');

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('error', 'This order has already been sent to Steadfast.');
        expect(courierCreateRequestCount())->toBe(0);
    });

    test('recording a consignment requires a note and an explicit portal confirmation', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        Http::fake();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), [
            'consignment_id' => 'SF-FOUND',
            'note' => '',
        ])->assertSessionHasErrors(['note', 'verified_in_portal']);

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
        Http::assertNothingSent();
    });

    test('a consignment the courier cannot confirm, or one already on another order, is refused', function () {
        $order = courierOrder();
        courierOrder(['steadfast_consignment_id' => 'SF-TAKEN']);
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        Http::fake([
            '*/status_by_cid/SF-TAKEN' => Http::response(['status' => 200, 'delivery_status' => 'in_review']),
            '*/status_by_cid/SF-TYPO' => Http::response(['status' => 404, 'message' => 'Consignment not found'], 404),
        ]);
        $payload = ['note' => 'Checked the Steadfast portal for this invoice.', 'verified_in_portal' => true];

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), $payload + ['consignment_id' => 'SF-TYPO'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'could not confirm consignment SF-TYPO'));
        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), $payload + ['consignment_id' => 'SF-TAKEN'])
            ->assertSessionHas('error', 'Consignment SF-TAKEN is already recorded on an order.');

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
    });

    test('recording a pathao consignment is unavailable until pathao order lookup is verified', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create(['courier' => 'pathao']);
        Http::fake();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), [
            'consignment_id' => 'PA-FOUND',
            'note' => 'Found it in the Pathao merchant panel.',
            'verified_in_portal' => true,
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, "Recording a Pathao consignment ID by hand isn't available yet"));

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
        expect($order->refresh()->pathao_consignment_id)->toBeNull();
        Http::assertNothingSent();
    });

    test('confirming not created releases the claim and allows exactly one new send', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create(['courier' => 'pathao']);
        Http::fake(['*/create_order' => Http::response(steadfastCreated('SF-AFTER'))]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmNotCreated', [$order, $submission]), [
            'note' => 'No parcel for this invoice in the Pathao panel.',
            'confirmed' => true,
        ])->assertSessionHas('success');

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::FAILED)
            ->and($submission->active_order_id)->toBeNull()
            ->and($submission->resolution)->toBe('confirmed_not_created')
            ->and($submission->resolved_by_admin_id)->toBe($this->admin->id);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('success');

        expect($order->refresh()->steadfast_consignment_id)->toBe('SF-AFTER');
        expect(courierCreateRequestCount())->toBe(1);
    });

    test('a submission still in progress cannot be reconciled', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->create();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmNotCreated', [$order, $submission]), [
            'note' => 'Trying to release it early.',
            'confirmed' => true,
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'still in progress'));

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::SUBMITTING)
            ->and($submission->active_order_id)->toBe($order->id);
    });

    test('a late courier response cannot overwrite an admin reconciliation decision', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        $service = app(CourierSubmissionService::class);

        $service->confirmNotCreated($submission, $this->admin, 'Not in the Steadfast portal at 10:00.');

        expect($service->markSent($submission, ['consignment_id' => 'SF-LATE', 'tracking_code' => null]))->toBeFalse();
        $service->markUnknown($submission, CourierSubmissionException::unknown('server_error', 'Late 500'));
        $service->markFailed($submission, CourierSubmissionException::notSent('Late failure'));

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::FAILED)
            ->and($submission->resolution)->toBe('confirmed_not_created')
            ->and($submission->consignment_id)->toBeNull();
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
        expect(OrderStatusHistory::where('order_id', $order->id)->latest('id')->value('notes'))
            ->toContain('returned consignment SF-LATE')
            ->toContain('possible duplicate consignment');
    });

    test('reconciliation requires the orders update permission', function () {
        $order = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        $viewer = Admin::create(['name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => bcrypt('password'), 'permissions' => ['orders' => []]]);

        actingAs($viewer, 'admin')->post(route('admin.orders.courier.confirmNotCreated', [$order, $submission]), [
            'note' => 'Should not be allowed to do this.',
            'confirmed' => true,
        ])->assertForbidden();

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
    });

    test('a submission can only be reconciled through its own order', function () {
        $order = courierOrder();
        $otherOrder = courierOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmNotCreated', [$otherOrder, $submission]), [
            'note' => 'Wrong order in the URL.',
            'confirmed' => true,
        ])->assertNotFound();

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
    });
});

describe('legacy backfill', function () {
    test('records every existing consignment, keeps both IDs on dual-courier orders, and is safe to re-run', function () {
        $both = courierOrder(['pathao_consignment_id' => 'PA-OLD', 'pathao_sent_at' => now()->subDays(3), 'steadfast_consignment_id' => 'SF-OLD', 'steadfast_tracking_code' => 'TRK-OLD', 'steadfast_sent_at' => now()->subDays(2)]);
        $pathaoOnly = courierOrder(['pathao_consignment_id' => 'PA-ONLY']);
        $trashed = courierOrder(['steadfast_consignment_id' => 'SF-TRASHED']);
        $trashed->delete();
        $neverSent = courierOrder();
        $columnsBefore = Order::withTrashed()->orderBy('id')->get(['id', 'pathao_consignment_id', 'steadfast_consignment_id', 'steadfast_tracking_code'])->toArray();
        $backfill = require database_path('migrations/2026_10_30_073623_backfill_legacy_courier_submissions.php');

        $backfill->up();
        $backfill->up();

        expect(CourierSubmission::count())->toBe(4);
        expect(CourierSubmission::where('order_id', $both->id)->orderBy('courier')->get(['courier', 'consignment_id', 'tracking_code'])->toArray())->toBe([
            ['courier' => 'pathao', 'consignment_id' => 'PA-OLD', 'tracking_code' => null],
            ['courier' => 'steadfast', 'consignment_id' => 'SF-OLD', 'tracking_code' => 'TRK-OLD'],
        ]);
        expect(CourierSubmission::where('order_id', $pathaoOnly->id)->value('consignment_id'))->toBe('PA-ONLY');
        expect(CourierSubmission::where('order_id', $trashed->id)->value('consignment_id'))->toBe('SF-TRASHED');
        expect(CourierSubmission::where('order_id', $neverSent->id)->exists())->toBeFalse();
        expect(CourierSubmission::whereNotNull('active_order_id')->exists())->toBeFalse();
        expect(CourierSubmission::where('state', '!=', 'sent')->orWhere('source', '!=', 'legacy_backfill')->exists())->toBeFalse();
        expect(Order::withTrashed()->orderBy('id')->get(['id', 'pathao_consignment_id', 'steadfast_consignment_id', 'steadfast_tracking_code'])->toArray())->toBe($columnsBefore);

        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $both))->assertSessionHas('error', 'This order has already been sent to Pathao.');

        $backfill->down();
        expect(CourierSubmission::count())->toBe(0);
        expect(Order::withTrashed()->orderBy('id')->get(['id', 'pathao_consignment_id', 'steadfast_consignment_id', 'steadfast_tracking_code'])->toArray())->toBe($columnsBefore);
    });
});
