<?php

declare(strict_types=1);

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\OrderController;
use App\Models\Admin;
use App\Models\CourierDeliveryEvidence;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use App\Services\CourierEvidenceChain;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierSubmissionService;
use App\Services\RefundService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * COURIER-SEND-RACE-001 regression tests against real MySQL/InnoDB.
 *
 * Each test starts a local fake courier (tests/Fixtures/fake_courier_server.php
 * under `php -S`) and runs the real admin send action in separate PHP
 * processes, so the claim really is contended by independent connections.
 * The fake courier logs every create request it receives — "exactly one
 * create" is asserted from that log, not from the application's own view.
 *
 * Runs only when COURIER_RACE_TEST_DATABASE names a disposable database
 * (^shoppydrive_test_[a-z0-9_]+$) on a local MySQL; skipped otherwise.
 */
const COURIER_RACE_SENDER_SCRIPT = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "Refusing to run against database {$database}.");
    exit(3);
}

[, $orderId, $action, $startAt] = $argv;
$order = App\Models\Order::findOrFail((int) $orderId);
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

if ($action === 'record') {
    // Manual Steadfast reconciliation: argv[4] consignment ID, argv[5] admin id.
    $submission = App\Models\CourierSubmission::where('order_id', $order->id)->latest('id')->firstOrFail();

    try {
        $app->make(App\Services\CourierSubmissionService::class)->recordConsignment(
            $submission, App\Models\Admin::findOrFail((int) $argv[5]), $argv[4], null, 'Verified in the Steadfast portal (race test).'
        );
        echo json_encode(['success' => 'recorded', 'error' => null]);
    } catch (App\Exceptions\CourierSubmissionBlockedException $e) {
        echo json_encode(['success' => null, 'error' => $e->getMessage()]);
    }

    return;
}

$response = $app->make(App\Http\Controllers\Admin\OrderController::class)
    ->{$action === 'pathao' ? 'sendToPathao' : 'sendToSteadfast'}($order);

echo json_encode(['success' => $response->getSession()?->get('success'), 'error' => $response->getSession()?->get('error')]);
PHP;

function courierRaceDatabase(): ?string
{
    $database = env('COURIER_RACE_TEST_DATABASE');

    if (! $database || preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
        test()->markTestSkipped('Set COURIER_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database to run this test.');

        return null;
    }

    config(['database.connections.mysql.database' => $database]);
    // Leave RefreshDatabase's wrapping transaction: the sender processes
    // must see this test's committed rows.
    DB::purge('mysql');

    $params = config('database.connections.mysql');

    if (! in_array($params['host'], ['127.0.0.1', 'localhost'], true) || (string) $params['port'] !== '3306') {
        test()->markTestSkipped("MySQL host/port {$params['host']}:{$params['port']} is not local — refusing to connect.");

        return null;
    }

    Setting::query()->delete();
    foreach ([
        'steadfast_api_key' => 'api-key', 'steadfast_secret_key' => 'secret-key',
        'pathao_environment' => 'sandbox', 'pathao_client_id' => 'client-id', 'pathao_client_secret' => 'client-secret',
        'pathao_username' => 'merchant@example.com', 'pathao_password' => 'secret', 'pathao_store_id' => '555',
        'pathao_token' => ['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()],
    ] as $key => $value) {
        Setting::create(['key' => $key, 'value' => $value, 'group' => 'courier']);
    }

    return $database;
}

final class FakeCourier
{
    public readonly string $url;

    private readonly InvokedProcess $server;

    private readonly string $log;

    private readonly string $script;

    /** @param  array<string, list<array{delay?: float, status?: int}>>  $script */
    public function __construct(array $script = [])
    {
        $directory = sys_get_temp_dir().'/fake-courier-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $this->log = "{$directory}/requests.log";
        $this->script = "{$directory}/script.json";
        touch($this->log);
        file_put_contents($this->script, json_encode((object) $script));

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) stream_socket_get_name($probe, false), strrpos((string) stream_socket_get_name($probe, false), ':') + 1);
        fclose($probe);
        $this->url = "http://127.0.0.1:{$port}";

        $this->server = Process::env([
            'FAKE_COURIER_LOG' => $this->log,
            'FAKE_COURIER_SCRIPT' => $this->script,
            'PHP_CLI_SERVER_WORKERS' => '4',
        ])->forever()->start(['php', '-S', "127.0.0.1:{$port}", base_path('tests/Fixtures/fake_courier_server.php')]);

        waitUntil(fn () => @fsockopen('127.0.0.1', $port) !== false, 'the fake courier to start');
    }

    /** @return list<array<string, mixed>> */
    public function createRequests(): array
    {
        return array_values(array_map(fn (string $line) => json_decode($line, true), array_filter(explode("\n", (string) file_get_contents($this->log)))));
    }

    /**
     * @param  array<string, string>  $env
     * @param  list<string>  $extraArgs
     */
    public function startSender(Order $order, string $courier, float $startAt, array $env = [], array $extraArgs = []): InvokedProcess
    {
        return Process::path(base_path())->env([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'STEADFAST_BASE_URL' => $this->url,
            'PATHAO_SANDBOX_BASE_URL' => $this->url,
            ...$env,
        ])->timeout(90)->start(['php', '-r', COURIER_RACE_SENDER_SCRIPT, (string) $order->id, $courier, (string) $startAt, ...$extraArgs]);
    }

    public function stop(): void
    {
        $this->server->signal(SIGTERM);
    }
}

function waitUntil(Closure $condition, string $what, float $seconds = 20): void
{
    $deadline = microtime(true) + $seconds;

    while (! $condition()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out waiting for {$what}.");
        }

        usleep(50_000);
    }
}

/** @return array{success: ?string, error: ?string} */
function senderOutcome(InvokedProcess $sender): array
{
    $result = $sender->wait();

    expect($result->exitCode())->toBe(0, $result->errorOutput());

    return json_decode($result->output(), true);
}

function raceOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-COURIER-RACE-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => OrderStatus::PROCESSING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Race Test', 'phone' => '01712345678', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => [],
    ]);
}

function sendSimultaneously(FakeCourier $courier, Order $order, string $first, string $second): array
{
    $startAt = microtime(true) + 4;
    $a = $courier->startSender($order, $first, $startAt);
    $b = $courier->startSender($order, $second, $startAt);

    return [senderOutcome($a), senderOutcome($b)];
}

$mysqlOnly = fn () => DB::connection()->getDriverName() !== 'mysql';

it('books exactly one consignment when two admins send the same order simultaneously', function (string $first, string $second) {
    if (courierRaceDatabase() === null) {
        return;
    }

    $courier = new FakeCourier;

    try {
        $order = raceOrder();
        $outcomes = sendSimultaneously($courier, $order, $first, $second);

        expect($courier->createRequests())->toHaveCount(1);
        expect(collect($outcomes)->whereNotNull('success'))->toHaveCount(1);
        expect(collect($outcomes)->whereNotNull('error')->first()['error'])
            ->toContain('in progress or its outcome is unresolved');

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::SENT)
            ->and($submission->active_order_id)->toBeNull()
            ->and($submission->consignment_id)->toBe($courier->createRequests()[0]['consignment_id']);
        expect($order->refresh()->{"{$submission->courier}_consignment_id"})->toBe($submission->consignment_id);
        expect(OrderStatusHistory::where('order_id', $order->id)->where('notes', 'like', 'Sent to%')->count())->toBe(1);
    } finally {
        $courier->stop();
    }
})->with([
    'Pathao vs Pathao' => ['pathao', 'pathao'],
    'Steadfast vs Steadfast' => ['steadfast', 'steadfast'],
    'Pathao vs Steadfast' => ['pathao', 'steadfast'],
])->skip($mysqlOnly, 'requires a real MySQL connection');

it('keeps the claim when the courier accepts but the response times out, and blocks the next send', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = raceOrder();
    $courier = new FakeCourier([$order->order_number => [['delay' => 3]]]);

    try {
        $timedOut = senderOutcome($courier->startSender($order, 'steadfast', microtime(true), ['STEADFAST_TIMEOUT' => '1']));
        expect($timedOut['error'])->toContain('did not confirm this booking');

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->active_order_id)->toBe($order->id)
            ->and($submission->failure_kind)->toBe('connection_interrupted')
            ->and($submission->curl_errno)->toBe(28)
            ->and($submission->request_bytes_sent)->toBeGreaterThan(0);

        $retry = senderOutcome($courier->startSender($order, 'steadfast', microtime(true)));
        expect($retry['error'])->toContain('in progress or its outcome is unresolved');

        // The courier did create one consignment; the app never sent a second request.
        expect($courier->createRequests())->toHaveCount(1);
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('releases the claim after a definite rejection so a corrected send can succeed', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = raceOrder();
    $courier = new FakeCourier([$order->order_number => [['status' => 422, 'delay' => 0]]]);

    try {
        $rejected = senderOutcome($courier->startSender($order, 'pathao', microtime(true)));
        expect($rejected['error'])->toBe('Pathao: Invalid recipient phone');
        expect(CourierSubmission::sole()->state)->toBe(CourierSubmissionState::FAILED);

        $retried = senderOutcome($courier->startSender($order, 'pathao', microtime(true)));
        expect($retried['success'])->toBe('Order sent to Pathao for pickup.');

        expect($courier->createRequests())->toHaveCount(2);
        expect(CourierSubmission::where('state', CourierSubmissionState::SENT)->count())->toBe(1);
        expect($order->refresh()->pathao_consignment_id)->toBe($courier->createRequests()[1]['consignment_id']);
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('treats a sender that died mid-request as unknown once stale, and never sends again on its own', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = raceOrder();
    $courier = new FakeCourier([$order->order_number => [['delay' => 10]]]);

    try {
        $sender = $courier->startSender($order, 'steadfast', microtime(true));
        waitUntil(fn () => count($courier->createRequests()) === 1, 'the create request to reach the courier');
        $sender->signal(SIGKILL);
        $sender->wait();

        $submission = CourierSubmission::sole();
        expect($submission->state)->toBe(CourierSubmissionState::SUBMITTING);

        // A fresh attempt is still "in progress" and blocks a new send…
        $blocked = senderOutcome($courier->startSender($order, 'steadfast', microtime(true)));
        expect($blocked['error'])->toContain('in progress or its outcome is unresolved');

        // …and once it is stale it becomes unknown — still blocking.
        $submission->forceFill(['claimed_at' => now()->subMinutes(CourierSubmission::STALE_AFTER_MINUTES + 1)])->save();
        $stillBlocked = senderOutcome($courier->startSender($order, 'pathao', microtime(true)));
        expect($stillBlocked['error'])->toContain('in progress or its outcome is unresolved');

        expect($submission->refresh()->state)->toBe(CourierSubmissionState::UNKNOWN)
            ->and($submission->failure_kind)->toBe('stale')
            ->and($submission->active_order_id)->toBe($order->id);
        expect($courier->createRequests())->toHaveCount(1);
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('does not let a late courier response overwrite an admin reconciliation', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = raceOrder();
    $admin = Admin::create(['name' => 'Race Admin', 'email' => 'race-admin-'.uniqid().'@example.com', 'password' => bcrypt('irrelevant')]);
    $courier = new FakeCourier([$order->order_number => [['delay' => 6]]]);

    try {
        $sender = $courier->startSender($order, 'steadfast', microtime(true));
        waitUntil(fn () => count($courier->createRequests()) === 1, 'the create request to reach the courier');

        // While the request is still in flight, the attempt goes stale and an
        // admin (wrongly) confirms in the portal that nothing was created.
        $submission = CourierSubmission::sole();
        $submission->forceFill(['claimed_at' => now()->subMinutes(CourierSubmission::STALE_AFTER_MINUTES + 1)])->save();
        app(CourierSubmissionService::class)->confirmNotCreated($submission, $admin, 'Not visible in the Steadfast portal yet.');

        $late = senderOutcome($sender);
        expect($late['error'])->toContain('had already been resolved');

        $submission->refresh();
        expect($submission->state)->toBe(CourierSubmissionState::FAILED)
            ->and($submission->resolution)->toBe('confirmed_not_created')
            ->and($submission->resolved_by_admin_id)->toBe($admin->id)
            ->and($submission->consignment_id)->toBeNull();
        expect($order->refresh()->steadfast_consignment_id)->toBeNull();
        expect(OrderStatusHistory::where('order_id', $order->id)->latest('id')->value('notes'))
            ->toContain('returned consignment '.$courier->createRequests()[0]['consignment_id'])
            ->toContain('possible duplicate consignment');
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('refuses to cancel or mark refunded an order whose courier booking is in flight', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = raceOrder();
    $courier = new FakeCourier([$order->order_number => [['delay' => 4]]]);

    try {
        $sender = $courier->startSender($order, 'steadfast', microtime(true));
        waitUntil(fn () => count($courier->createRequests()) === 1, 'the create request to reach the courier');

        // Both calls share this process's session, so read each flash right away.
        $controller = app(OrderController::class);
        $cancelError = $controller->cancel(Request::create('/', 'PATCH', ['reason' => 'Customer changed mind']), $order)->getSession()->get('error');
        $refundError = $controller->markRefunded(
            Request::create('/', 'PATCH', ['refund_amount' => 100, 'refund_method' => 'bkash']),
            $order,
            app(RefundService::class),
        )->getSession()->get('error');

        expect($cancelError)->toContain("can't be cancelled while a courier submission is in progress");
        expect($refundError)->toContain("can't be marked refunded while a courier submission is in progress");

        expect(senderOutcome($sender)['success'])->toBe('Order sent to Steadfast for pickup.');

        // Neither blocked action left a trace; the booking completed normally.
        $order->refresh();
        expect($order->status)->not->toBeIn([OrderStatus::CANCELLED, OrderStatus::REFUNDED])
            ->and($order->cancellation_reason)->toBeNull()
            ->and($order->steadfast_consignment_id)->toBe($courier->createRequests()[0]['consignment_id']);
        expect(OrderStatusHistory::where('order_id', $order->id)->whereIn('status', ['cancelled', 'refunded'])->exists())->toBeFalse();
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('backfills legacy consignments on MySQL, keeping both IDs of a dual-courier order, idempotently', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $both = raceOrder();
    $both->forceFill(['pathao_consignment_id' => 'PA-LEGACY', 'steadfast_consignment_id' => 'SF-LEGACY', 'steadfast_tracking_code' => 'TRK-LEGACY'])->save();
    $before = DB::table('orders')->where('id', $both->id)->first();
    $backfill = require database_path('migrations/2026_10_30_073623_backfill_legacy_courier_submissions.php');

    $backfill->up();
    $backfill->up();

    $rows = CourierSubmission::where('order_id', $both->id)->orderBy('courier')->get();
    expect($rows->pluck('consignment_id')->all())->toBe(['PA-LEGACY', 'SF-LEGACY'])
        ->and($rows->pluck('active_order_id')->filter()->all())->toBe([])
        ->and($rows->pluck('source')->unique()->all())->toBe(['legacy_backfill']);
    expect(DB::table('orders')->where('id', $both->id)->first())->toEqual($before);
})->skip($mysqlOnly, 'requires a real MySQL connection');

/*
 * COURIER-SEND-RACE-002
 */
it('lets simultaneous claims for different orders all succeed without an InnoDB deadlock', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $courier = new FakeCourier;

    try {
        $orders = collect(range(1, 4))->map(fn () => raceOrder());
        $deadlocksBefore = (int) DB::selectOne("SELECT COUNT AS c FROM information_schema.INNODB_METRICS WHERE NAME = 'lock_deadlocks'")->c;

        $startAt = microtime(true) + 4;
        $senders = $orders->map(fn (Order $order) => $courier->startSender($order, 'steadfast', $startAt));
        $outcomes = $senders->map(fn (InvokedProcess $sender) => senderOutcome($sender));

        expect($outcomes->pluck('success')->filter()->count())->toBe(4);
        expect((int) DB::selectOne("SELECT COUNT AS c FROM information_schema.INNODB_METRICS WHERE NAME = 'lock_deadlocks'")->c - $deadlocksBefore)->toBe(0);
        expect($courier->createRequests())->toHaveCount(4);
        expect(CourierSubmission::where('state', CourierSubmissionState::SENT)->count())->toBe(4);
        $orders->each(fn (Order $order) => expect($order->refresh()->steadfast_consignment_id)->not->toBeNull());
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('lets only one of two simultaneous reconciliations record the same consignment ID', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $courier = new FakeCourier;

    try {
        $admin = Admin::create(['name' => 'Race Admin', 'email' => 'race-admin-'.uniqid().'@example.com', 'password' => bcrypt('irrelevant')]);
        $orders = collect([raceOrder(), raceOrder()]);
        $orders->each(fn (Order $order) => CourierSubmission::factory()->forOrder($order)->unknown()->create());

        $startAt = microtime(true) + 4;
        $senders = $orders->map(fn (Order $order) => $courier->startSender($order, 'record', $startAt, [], ['SF-SHARED-1', (string) $admin->id]));
        $outcomes = $senders->map(fn (InvokedProcess $sender) => senderOutcome($sender));

        expect($outcomes->pluck('success')->filter()->count())->toBe(1);
        expect($outcomes->pluck('error')->filter()->first())->toContain('SF-SHARED-1 is already recorded');
        expect(Order::where('steadfast_consignment_id', 'SF-SHARED-1')->count())->toBe(1);
        expect(CourierSubmission::where('state', CourierSubmissionState::UNKNOWN)->whereNotNull('active_order_id')->count())->toBe(1);
        expect($courier->createRequests())->toHaveCount(0);
    } finally {
        $courier->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('keeps the courier-returned ID and the claim when finalization fails on MySQL, then recovers through reconciliation', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    // A real MySQL error inside the finalization transaction: the "Sent to …"
    // order-history insert is the last write markSent() makes. Disposable
    // test database only (courierRaceDatabase() enforces the name/host).
    $dropTrigger = fn () => DB::unprepared('DROP TRIGGER IF EXISTS courier_race_fail_finalization');
    $courier = null;

    // Everything from the trigger onwards is inside try, so finally always
    // drops it — even if creating the order or starting the fake courier fails.
    try {
        $dropTrigger();
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER courier_race_fail_finalization BEFORE INSERT ON order_status_histories FOR EACH ROW
            BEGIN
                IF NEW.notes LIKE 'Sent to %' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated finalization failure (race test trigger)';
                END IF;
            END
            SQL);

        $order = raceOrder();
        $courier = new FakeCourier;

        $failed = senderOutcome($courier->startSender($order, 'pathao', microtime(true)));

        expect($courier->createRequests())->toHaveCount(1);
        $returnedId = $courier->createRequests()[0]['consignment_id'];
        expect($failed['error'])->toContain("Pathao created consignment {$returnedId}, but it could not be saved to this order");

        // Read back as committed rows: the returned ID survived the rolled-back finalization.
        $attempt = DB::table('courier_submissions')->where('order_id', $order->id)->sole();
        expect($attempt->state)->toBe('unknown')
            ->and($attempt->failure_kind)->toBe('finalization_failed')
            ->and((int) $attempt->active_order_id)->toBe($order->id)
            ->and($attempt->returned_consignment_id)->toBe($returnedId)
            ->and($attempt->returned_at)->not->toBeNull()
            ->and($attempt->consignment_id)->toBeNull();
        expect(DB::table('orders')->where('id', $order->id)->value('pathao_consignment_id'))->toBeNull();
        expect(OrderStatusHistory::where('order_id', $order->id)->where('notes', 'like', 'Sent to%')->exists())->toBeFalse();

        // No second create request while it is unresolved — to either courier.
        $blocked = senderOutcome($courier->startSender($order, 'steadfast', microtime(true)));
        expect($blocked['error'])->toContain('in progress or its outcome is unresolved');
        expect($courier->createRequests())->toHaveCount(1);

        $dropTrigger();
        $admin = Admin::create(['name' => 'Recovery Admin', 'email' => 'recovery-admin-'.uniqid().'@example.com', 'password' => bcrypt('irrelevant')]);
        app(CourierSubmissionService::class)->confirmReturnedConsignment(CourierSubmission::sole(), $admin, 'Pathao returned this ID for our request; parcel visible in the panel.');

        $recovered = CourierSubmission::sole();
        expect($recovered->state)->toBe(CourierSubmissionState::SENT)
            ->and($recovered->active_order_id)->toBeNull()
            ->and($recovered->consignment_id)->toBe($returnedId)
            ->and($recovered->resolution)->toBe('courier_returned_id_confirmed')
            ->and($recovered->resolved_by_admin_id)->toBe($admin->id);
        expect($order->refresh()->pathao_consignment_id)->toBe($returnedId);
        expect(OrderStatusHistory::where('order_id', $order->id)->latest('id')->value('notes'))
            ->toContain($returnedId)
            ->toContain('linked by Recovery Admin');
        expect($courier->createRequests())->toHaveCount(1);
    } finally {
        $dropTrigger();
        $courier?->stop();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

// ── PAY-RETURN-001: structured courier evidence on real MySQL ───────────────

/** A child process that runs one courier status sync: the new mapper, or a replica of the production (G1) mapper. */
const COURIER_EVIDENCE_SYNC_SCRIPT = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "Refusing to run against database {$database}.");
    exit(3);
}

[, $orderId, $mode, $raw, $startAt] = $argv;
$order = App\Models\Order::findOrFail((int) $orderId);
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

if ($mode === 'g1') {
    // Production (pre-PAY-RETURN-001) shape: no row lock, raw update, separate history insert, no evidence.
    Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update(['steadfast_status' => $raw]);
    App\Models\OrderStatusHistory::create(['order_id' => $order->id, 'status' => $order->status->value, 'notes' => 'Steadfast reported "'.App\Services\CourierOrderStatusMapper::label($raw).'".']);
} else {
    App\Services\CourierOrderStatusMapper::sync($order, 'steadfast', $raw, source: App\Enums\CourierEvidenceSource::STEADFAST_WEBHOOK);
}

echo 'done';
PHP;

function startEvidenceSync(Order $order, string $mode, string $raw, float $startAt): InvokedProcess
{
    return Process::path(base_path())->env([
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(),
        'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    ])->timeout(90)->start(['php', '-r', COURIER_EVIDENCE_SYNC_SCRIPT, (string) $order->id, $mode, $raw, (string) $startAt]);
}

/** A delivered-in-progress order whose Steadfast consignment was created by this app (send anchor). */
function evidenceRaceOrder(): Order
{
    $order = raceOrder();
    $submission = CourierSubmission::factory()->forOrder($order)->create(['courier' => 'steadfast']);
    app(CourierSubmissionService::class)->markSent($submission, ['consignment_id' => 'SF-EV-'.$order->id, 'tracking_code' => 'T']);

    return $order->refresh();
}

it('rolls back the whole courier status change when the evidence insert fails on MySQL (PAY-RETURN-001)', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $dropTrigger = fn () => DB::unprepared('DROP TRIGGER IF EXISTS pay_return_fail_evidence');

    try {
        $dropTrigger();
        $order = evidenceRaceOrder();
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER pay_return_fail_evidence BEFORE INSERT ON courier_delivery_evidence FOR EACH ROW
            BEGIN
                IF NEW.evidence_type = 'transition' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated evidence failure (PAY-RETURN-001 test trigger)';
                END IF;
            END
            SQL);
        $historyBefore = OrderStatusHistory::where('order_id', $order->id)->count();

        expect(fn () => CourierOrderStatusMapper::sync($order, 'steadfast', 'delivered_parcels'))->toThrow(QueryException::class);

        $fresh = DB::table('orders')->where('id', $order->id)->first();
        expect($fresh->status)->toBe('processing')
            ->and($fresh->steadfast_status)->toBeNull()
            ->and($fresh->delivered_at)->toBeNull()
            ->and(OrderStatusHistory::where('order_id', $order->id)->count())->toBe($historyBefore)
            ->and(CourierDeliveryEvidence::where('order_id', $order->id)->where('evidence_type', 'transition')->count())->toBe(0);
    } finally {
        $dropTrigger();
    }
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('never loses evidence when a partial and a full report arrive at the same moment (PAY-RETURN-001)', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = evidenceRaceOrder();
    $startAt = microtime(true) + 4;
    $a = startEvidenceSync($order, 'new', 'partial_parcels', $startAt);
    $b = startEvidenceSync($order, 'new', 'delivered_parcels', $startAt);
    expect($a->wait()->output())->toBe('done')->and($b->wait()->output())->toBe('done');

    $rows = CourierDeliveryEvidence::where('order_id', $order->id)->whereIn('evidence_type', ['transition', 'observation'])->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[1]->previous_raw_status)->toBe($rows[0]->raw_status) // serialized on the order row lock: a continuous chain
        ->and($rows->pluck('is_partial')->contains(true))->toBeTrue()
        ->and(CourierDeliveryEvidence::where('order_id', $order->id)->where('evidence_type', 'gap')->count())->toBe(0);

    makeCourierReadinessCurrent();
    expect(app(CourierEvidenceChain::class)->eligibility($order->refresh())['eligible'])->toBeFalse();
})->skip($mysqlOnly, 'requires a real MySQL connection');

it('detects a stale production-shape writer racing the new mapper (PAY-RETURN-001)', function () {
    if (courierRaceDatabase() === null) {
        return;
    }

    $order = evidenceRaceOrder();
    $startAt = microtime(true) + 4;
    $stale = startEvidenceSync($order, 'g1', 'waiting_approval', $startAt);
    $fresh = startEvidenceSync($order, 'new', 'in_review', $startAt);
    expect($stale->wait()->output())->toBe('done')->and($fresh->wait()->output())->toBe('done');

    // The next trusted report (even unchanged) records whatever the stale writer left behind.
    CourierOrderStatusMapper::sync($order->refresh(), 'steadfast', (string) $order->steadfast_status);

    expect(CourierDeliveryEvidence::where('order_id', $order->id)->where('evidence_type', 'gap')->count())->toBeGreaterThan(0);
})->skip($mysqlOnly, 'requires a real MySQL connection');
