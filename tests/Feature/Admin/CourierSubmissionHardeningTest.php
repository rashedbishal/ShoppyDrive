<?php

declare(strict_types=1);

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\CourierSubmissionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * COURIER-SEND-RACE-002 on SQLite: recovery after a courier success whose
 * finalization fails (H1), consignment-ID uniqueness (M2), audit-history
 * preservation and rollback guards (M3), the shipping-address guard (L3),
 * and remaining coverage gaps. Real MySQL concurrency for M1/M2 lives in
 * tests/Feature/CourierSendConcurrencyRaceTest.php.
 */
function hardeningOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-HARDEN-'.strtoupper(bin2hex(random_bytes(3))),
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

function hardeningCreateRequests(): int
{
    return Http::recorded(fn ($request) => str_ends_with($request->url(), '/create_order') || str_ends_with($request->url(), '/aladdin/api/v1/orders'))->count();
}

/** Makes the final "Sent to …" history write fail, i.e. finalization fails after the courier succeeded. */
function failFinalization(): void
{
    OrderStatusHistory::creating(function (OrderStatusHistory $history): void {
        if (str_starts_with((string) $history->notes, 'Sent to ')) {
            throw new RuntimeException('Simulated database failure while finalizing.');
        }
    });
}

function migrationFile(string $name): object
{
    return require database_path("migrations/{$name}.php");
}

/**
 * Tests that run a migration's up()/down() change the schema inside the
 * test. That is only undone afterwards on SQLite, where DDL is
 * transactional; on MySQL/MariaDB every statement commits immediately and
 * would really drop indexes or tables in whatever database the run uses.
 */
const SCHEMA_CHANGING_TEST_SKIP_REASON = 'changes the schema inside the test transaction — only reverted afterwards on SQLite (MySQL/MariaDB DDL commits immediately)';

function notSqlite(): bool
{
    return DB::connection()->getDriverName() !== 'sqlite';
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
    Setting::create(['key' => 'pathao_token', 'value' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()], 'group' => 'courier']);
});

describe('H1: recovery after the courier succeeded', function () {
    test('a database failure after a successful courier response keeps the returned ID and the claim, and is reported', function () {
        $order = hardeningOrder();
        Http::fake(['*/create_order' => Http::response(['status' => 200, 'consignment' => ['consignment_id' => 'SF-RET-1', 'tracking_code' => 'TRK-RET-1', 'status' => 'in_review']])]);
        Log::spy();
        failFinalization();

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'created consignment SF-RET-1, but it could not be saved to this order'));

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->failure_kind)->toBe('finalization_failed')
            ->and($submission->active_order_id)->toBe($order->id)
            ->and($submission->returned_consignment_id)->toBe('SF-RET-1')
            ->and($submission->returned_tracking_code)->toBe('TRK-RET-1')
            ->and($submission->returned_at)->not->toBeNull()
            ->and($submission->consignment_id)->toBeNull();
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['consignment_id'] === 'SF-RET-1')->once();

        // Recovery never sends a second create request.
        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'in progress or its outcome is unresolved'));
        expect(hardeningCreateRequests())->toBe(1);
    });

    test('a pathao attempt can be reconciled with the consignment ID pathao returned for it', function () {
        $order = hardeningOrder();
        Http::fake(['*/aladdin/api/v1/orders' => Http::response(['type' => 'success', 'data' => ['consignment_id' => 'PA-RET-1', 'order_status' => 'Pending', 'delivery_fee' => 60]])]);
        failFinalization();

        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))->assertSessionHas('error');
        $submission = CourierSubmission::sole();

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmReturnedConsignment', [$order, $submission]), [
            'note' => 'Pathao returned this ID for our send; panel shows the parcel.',
            'confirmed' => true,
        ])->assertSessionHas('success');

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::SENT)
            ->and($submission->active_order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe('PA-RET-1')
            ->and($submission->resolution)->toBe('courier_returned_id_confirmed')
            ->and($submission->resolved_by_admin_id)->toBe($this->admin->id);
        expect($order->refresh()->pathao_consignment_id)->toBe('PA-RET-1');
        expect(OrderStatusHistory::where('order_id', $order->id)->pluck('notes')->implode(' '))->toContain('PA-RET-1');

        actingAs($this->admin, 'admin')->post(route('admin.orders.pathao.send', $order))->assertSessionHas('error', 'This order has already been sent to Pathao.');
        expect(hardeningCreateRequests())->toBe(1);
    });

    test('linking a returned consignment needs a note, a confirmation, and an ID the courier actually returned', function () {
        $order = hardeningOrder();
        $withoutReturnedId = CourierSubmission::factory()->forOrder($order)->unknown()->create(['courier' => 'pathao']);

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmReturnedConsignment', [$order, $withoutReturnedId]), ['note' => ''])
            ->assertSessionHasErrors(['note', 'confirmed']);
        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.confirmReturnedConsignment', [$order, $withoutReturnedId]), [
            'note' => 'Trying to link without an ID.',
            'confirmed' => true,
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, "didn't return a consignment ID"));

        expect($withoutReturnedId->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
        expect($order->refresh()->pathao_consignment_id)->toBeNull();
    });

    test('a late courier response after a manual decision is kept only as evidence', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        $service = app(CourierSubmissionService::class);

        $service->confirmNotCreated($submission, $this->admin, 'Not in the Steadfast portal at 10:00.');
        $service->recordReturnedConsignment($submission, ['consignment_id' => 'SF-LATE-1', 'tracking_code' => null]);
        expect($service->markSent($submission, ['consignment_id' => 'SF-LATE-1']))->toBeFalse();

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::FAILED)
            ->and($submission->resolution)->toBe('confirmed_not_created')
            ->and($submission->consignment_id)->toBeNull()
            ->and($submission->returned_consignment_id)->toBe('SF-LATE-1');
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
    });

    test('the first courier-returned ID is never overwritten', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create(['returned_consignment_id' => 'SF-FIRST', 'returned_at' => now()]);

        app(CourierSubmissionService::class)->recordReturnedConsignment($submission, ['consignment_id' => 'SF-SECOND']);

        expect($submission->refresh()->returned_consignment_id)->toBe('SF-FIRST');
    });

    test('the order page shows an attempt that went stale as unknown, with its returned ID', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->stale()->create(['returned_consignment_id' => 'SF-STALE-1', 'returned_at' => now()]);

        actingAs($this->admin, 'admin')->get(route('admin.orders.show', $order))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/orders/show')
                ->where('courierSubmission.id', $submission->id)
                ->where('courierSubmission.state', 'unknown')
                ->where('courierSubmission.failure_kind', 'stale')
                ->where('courierSubmission.returned_consignment_id', 'SF-STALE-1')
                ->where('courierSubmission.can_record_consignment', true));

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN);
    });
});

describe('M2: consignment ID uniqueness', function () {
    test('a consignment ID can belong to only one order, while unsent orders keep NULL', function () {
        $first = hardeningOrder(['steadfast_consignment_id' => 'SF-UNIQUE-1']);
        hardeningOrder();
        hardeningOrder();
        $second = hardeningOrder();

        expect(fn () => $second->update(['steadfast_consignment_id' => 'SF-UNIQUE-1']))->toThrow(UniqueConstraintViolationException::class);
        // The two columns are independent: the same value may appear once in each.
        expect(fn () => $second->refresh()->update(['pathao_consignment_id' => 'SF-UNIQUE-1']))->not->toThrow(UniqueConstraintViolationException::class);

        expect($first->refresh()->steadfast_consignment_id)->toBe('SF-UNIQUE-1');
        expect(Order::whereNull('steadfast_consignment_id')->count())->toBe(3);
    });

    test('a consignment another order takes at the last moment is refused cleanly and nothing changes', function () {
        $order = hardeningOrder();
        $rival = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        Http::fake(['*/status_by_cid/SF-CONTESTED' => Http::response(['status' => 200, 'delivery_status' => 'in_review'])]);

        // Passes the application-level "used elsewhere" check, then loses
        // to the unique index — as a concurrent reconciliation would.
        CourierSubmission::updating(function (CourierSubmission $updating) use ($rival): void {
            if ($updating->state === CourierSubmissionState::SENT) {
                DB::table('orders')->where('id', $rival->id)->update(['steadfast_consignment_id' => 'SF-CONTESTED']);
            }
        });

        actingAs($this->admin, 'admin')->post(route('admin.orders.courier.recordConsignment', [$order, $submission]), [
            'consignment_id' => 'SF-CONTESTED',
            'note' => 'Found it in the Steadfast portal for this invoice.',
            'verified_in_portal' => true,
        ])->assertSessionHas('error', 'Consignment SF-CONTESTED is already recorded on another order.');

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->active_order_id)->toBe($order->id);
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
    });

    test('the unique-index migration refuses duplicates, lists them, and changes nothing', function () {
        $migration = migrationFile('2026_10_30_085859_add_unique_consignment_indexes_to_orders_table');
        $migration->down();

        $a = hardeningOrder(['pathao_consignment_id' => 'PA-DUP']);
        $b = hardeningOrder(['pathao_consignment_id' => 'PA-DUP']);
        $c = hardeningOrder(['steadfast_consignment_id' => '']);
        $d = hardeningOrder(['steadfast_consignment_id' => '']);
        $before = Order::orderBy('id')->get(['id', 'pathao_consignment_id', 'steadfast_consignment_id'])->toArray();

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, "orders.pathao_consignment_id = PA-DUP on 2 orders (ids {$a->id}, {$b->id})");
        expect(Order::orderBy('id')->get(['id', 'pathao_consignment_id', 'steadfast_consignment_id'])->toArray())->toBe($before);

        $this->artisan('courier:check-consignment-duplicates')
            ->expectsOutputToContain('PA-DUP on 2 orders')
            ->expectsOutputToContain("(empty string) on 2 orders (ids {$c->id}, {$d->id})")
            ->assertFailed();

        DB::table('orders')->where('id', $b->id)->update(['pathao_consignment_id' => null]);
        DB::table('orders')->whereIn('id', [$c->id, $d->id])->update(['steadfast_consignment_id' => null]);

        $this->artisan('courier:check-consignment-duplicates')->assertSuccessful();
        $migration->up();
        expect(fn () => $b->refresh()->update(['pathao_consignment_id' => 'PA-DUP']))->toThrow(UniqueConstraintViolationException::class);
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);
});

describe('M3: courier history survives deletion and rollback', function () {
    test('permanently deleting an order keeps its courier history, detached', function () {
        $order = hardeningOrder(['status' => OrderStatus::CANCELLED, 'steadfast_consignment_id' => 'SF-KEPT']);
        $submission = CourierSubmission::factory()->forOrder($order)->create(['state' => CourierSubmissionState::SENT, 'active_order_id' => null, 'consignment_id' => 'SF-KEPT', 'resolution_note' => 'History to keep.']);

        actingAs($this->admin, 'admin')->delete(route('admin.orders.destroy', $order))->assertSessionHas('success');
        actingAs($this->admin, 'admin')->delete(route('admin.orders.forceDelete', $order->id))->assertSessionHas('success');

        expect(Order::withTrashed()->find($order->id))->toBeNull();
        $submission->refresh();
        expect($submission->order_id)->toBeNull()
            ->and($submission->reference)->toBe($order->order_number)
            ->and($submission->consignment_id)->toBe('SF-KEPT')
            ->and($submission->resolution_note)->toBe('History to keep.');
    });

    test('a customer deleting their account keeps the courier history of their orders', function () {
        $customer = User::factory()->create();
        $order = hardeningOrder(['user_id' => $customer->id, 'pathao_consignment_id' => 'PA-KEPT']);
        $submission = CourierSubmission::factory()->forOrder($order)->create(['courier' => 'pathao', 'state' => CourierSubmissionState::SENT, 'active_order_id' => null, 'consignment_id' => 'PA-KEPT']);

        $customer->forceDelete();

        expect(Order::withTrashed()->find($order->id))->toBeNull();
        expect($submission->refresh()->order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe('PA-KEPT');
    });

    test('rolling back the courier history table is refused while it holds real attempts', function () {
        $order = hardeningOrder();
        CourierSubmission::factory()->forOrder($order)->unknown()->create();

        expect(fn () => migrationFile('2026_10_30_073622_create_courier_submissions_table')->down())
            ->toThrow(RuntimeException::class, 'Refusing to drop courier_submissions');
        expect(CourierSubmission::count())->toBe(1);
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);

    test('backfilled rows are removed by the backfill rollback, after which the table may be dropped', function () {
        hardeningOrder(['steadfast_consignment_id' => 'SF-LEGACY-1']);
        $backfill = migrationFile('2026_10_30_073623_backfill_legacy_courier_submissions');
        $backfill->up();
        expect(CourierSubmission::where('source', 'legacy_backfill')->count())->toBe(1);

        $backfill->down();
        expect(CourierSubmission::count())->toBe(0);

        migrationFile('2026_10_30_073622_create_courier_submissions_table')->down();
        expect(Schema::hasTable('courier_submissions'))->toBeFalse();
        expect(Order::where('steadfast_consignment_id', 'SF-LEGACY-1')->exists())->toBeTrue();
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);

    test('the later courier-history migrations refuse rollbacks that would destroy audit data', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create(['returned_consignment_id' => 'SF-AUDIT-1', 'returned_at' => now()]);

        expect(fn () => migrationFile('2026_10_30_085856_add_returned_consignment_to_courier_submissions_table')->down())
            ->toThrow(RuntimeException::class, 'courier-returned consignment IDs');

        // A detached operational row: the operational-history guard (D1) is
        // checked first, so that is the reason given.
        $submission->forceFill(['order_id' => null])->save();
        expect(fn () => migrationFile('2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted')->down())
            ->toThrow(RuntimeException::class, 'contains real courier send attempts');

        expect($submission->refresh()->returned_consignment_id)->toBe('SF-AUDIT-1');
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);

    test('the foreign-key rollback refuses while operational history exists, even with no detached rows, and changes nothing', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->create(['state' => CourierSubmissionState::SENT, 'active_order_id' => null, 'consignment_id' => 'SF-OPERATIONAL-1']);
        expect(CourierSubmission::whereNull('order_id')->exists())->toBeFalse();

        expect(fn () => migrationFile('2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted')->down())
            ->toThrow(RuntimeException::class, 'contains real courier send attempts');

        // The SET NULL key is still in place: deleting the order keeps the row.
        $order->forceDelete();
        expect($submission->refresh()->order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe('SF-OPERATIONAL-1');
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);

    test('with only backfilled rows the foreign-key rollback still runs, unless one of them is detached', function () {
        $detachedOrder = hardeningOrder(['steadfast_consignment_id' => 'SF-LEGACY-DETACHED']);
        $attachedOrder = hardeningOrder(['pathao_consignment_id' => 'PA-LEGACY-ATTACHED']);
        migrationFile('2026_10_30_073623_backfill_legacy_courier_submissions')->up();
        $fkMigration = migrationFile('2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted');

        $detachedOrder->forceDelete();
        expect(fn () => $fkMigration->down())->toThrow(RuntimeException::class, 'belong to deleted orders');

        CourierSubmission::whereNull('order_id')->delete();
        $fkMigration->down();

        // Previous behaviour restored: the cascading key removes a deleted order's backfilled row.
        $attachedOrder->forceDelete();
        expect(CourierSubmission::count())->toBe(0);
    })->skip(fn () => notSqlite(), SCHEMA_CHANGING_TEST_SKIP_REASON);
});

describe('L3: shipping address during a courier submission', function () {
    test('the shipping address cannot change while a submission is unresolved', function (string $state) {
        $order = hardeningOrder();
        $factory = CourierSubmission::factory()->forOrder($order);
        ($state === 'unknown' ? $factory->unknown() : $factory)->create();

        actingAs($this->admin, 'admin')->patch(route('admin.orders.updateShippingAddress', $order), [
            'phone' => '01812345678', 'address_line1' => 'New House 9', 'city' => 'Chattogram', 'state' => 'Chattogram', 'country' => 'Bangladesh',
        ])->assertSessionHas('error', "The shipping address can't be changed while a courier submission is in progress or its outcome is unresolved. Resolve it in the Courier panel first.");

        expect($order->refresh()->shipping_address['address_line1'])->toBe('House 1');
    })->with(['submitting', 'unknown']);

    test('the shipping address can change again once the submission is resolved', function () {
        $order = hardeningOrder();
        $submission = CourierSubmission::factory()->forOrder($order)->unknown()->create();
        app(CourierSubmissionService::class)->confirmNotCreated($submission, $this->admin, 'Not in the Steadfast portal.');

        actingAs($this->admin, 'admin')->patch(route('admin.orders.updateShippingAddress', $order), [
            'phone' => '01812345678', 'address_line1' => 'New House 9', 'city' => 'Chattogram', 'state' => 'Chattogram', 'country' => 'Bangladesh',
        ])->assertSessionHas('success');

        expect($order->refresh()->shipping_address)->toMatchArray(['address_line1' => 'New House 9', 'name' => 'Rafiq Islam']);
    });
});

describe('remaining coverage', function () {
    test('HTTP 408 from the courier is an unknown outcome that keeps the claim', function () {
        $order = hardeningOrder();
        Http::fake(['*/create_order' => Http::response(['message' => 'Request Timeout'], 408)]);

        actingAs($this->admin, 'admin')->post(route('admin.orders.steadfast.send', $order))->assertSessionHas('error');

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->failure_kind)->toBe('ambiguous_http_status')
            ->and($submission->http_status)->toBe(408)
            ->and($submission->active_order_id)->toBe($order->id);
    });

    test('a webhook whose consignment belongs to a trashed order updates nothing', function () {
        Setting::updateOrCreate(['key' => 'steadfast_webhook_secret'], ['value' => 'hardening-secret', 'group' => 'courier']);
        $order = hardeningOrder(['status' => OrderStatus::CANCELLED, 'steadfast_consignment_id' => 'SF-TRASHED-1', 'steadfast_status' => 'in_review']);
        $order->delete();
        $body = json_encode(['notification_type' => 'delivery_status', 'consignment_id' => 'SF-TRASHED-1', 'status' => 'delivered']);

        $this->call('POST', route('steadfast.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => hash_hmac('sha256', $body, 'hardening-secret')], $body)
            ->assertOk();

        expect(Order::withTrashed()->find($order->id)->steadfast_status)->toBe('in_review');
    });
});
