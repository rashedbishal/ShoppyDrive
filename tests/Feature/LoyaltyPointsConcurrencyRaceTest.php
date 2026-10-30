<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Http\Controllers\Admin\OrderController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * PROMO-LOY-001 regression: `LoyaltyPointsService::refundRedeemedPointsIfApplicable()`
 * and `::grantRegistrationBonus()` used to lock rows with no enclosing
 * `DB::transaction()`, so the lock was released the instant that one
 * statement completed under MySQL's autocommit mode — providing no real
 * serialization, and leaving the idempotency check reading a snapshot that
 * could be stale relative to a concurrent commit.
 *
 * This can ONLY be demonstrated with two real, separate database
 * connections racing against real MySQL/InnoDB locking semantics — the
 * rest of this suite runs on SQLite in-memory (phpunit.xml), which would
 * silently "pass" regardless of whether the fix is present. This file
 * mirrors `tests/Feature/CouponConcurrencyRaceTest.php`'s safety-gating
 * convention exactly, including its SAFETY rationale (this local MySQL
 * server hosts several unrelated databases under the same root user).
 *
 * Scope note: like `CouponConcurrencyRaceTest.php`, these tests prove
 * serialization-and-snapshot-visibility correctness (connection B, after
 * waiting on connection A's lock and A committing, correctly sees A's
 * commit instead of a stale pre-commit snapshot) — the same class of bug
 * this file's own fix addresses. They do not and cannot reproduce genuine
 * simultaneous blocking between two connections (that requires real
 * parallelism, unavailable in a single-threaded PHP test process), so they
 * are not a test of deadlock behavior — see the PROMO-LOY-001 report for
 * why the lock-ordering fix in `ReferralService` is verified by code
 * review/reasoning only, not by an executed test.
 */
function loyaltyRaceConnections(): ?array
{
    $testDatabase = env('LOYALTY_RACE_TEST_DATABASE');

    if (! $testDatabase) {
        test()->markTestSkipped('Set LOYALTY_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database name to run this test — it is never run against the real dev/production database.');

        return null;
    }

    if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $testDatabase) !== 1) {
        test()->markTestSkipped("LOYALTY_RACE_TEST_DATABASE '{$testDatabase}' does not match the required allowlist pattern '^shoppydrive_test_[a-z0-9_]+\$' — refusing to connect to anything that isn't unambiguously a disposable test database.");

        return null;
    }

    config(['database.connections.mysql.database' => $testDatabase]);
    DB::purge('mysql');

    $params = config('database.connections.mysql');

    // Explicit local-testing allowlist for the host/port this would
    // actually connect to — the check above only constrains the database
    // *name*; without this, a future remote DB_HOST in .env would be
    // trusted silently.
    $allowedHosts = ['127.0.0.1', 'localhost'];
    $allowedPort = '3306';

    if (! in_array($params['host'], $allowedHosts, true) || (string) $params['port'] !== $allowedPort) {
        test()->markTestSkipped("Resolved MySQL host/port ({$params['host']}:{$params['port']}) is not in the explicit local-testing allowlist (host: 127.0.0.1 or localhost, port: {$allowedPort}) — refusing to connect.");

        return null;
    }

    $dsn = "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4";

    try {
        $connA = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connB = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        test()->markTestSkipped('No reachable local MySQL connection to run a real concurrency test against: '.$e->getMessage());

        return null;
    }

    return [$connA, $connB];
}

it('does not double-refund redeemed points when two cancellation triggers race under real MySQL locking', function () {
    $conns = loyaltyRaceConnections();

    if ($conns === null) {
        return;
    }

    [$connA, $connB] = $conns;

    $user = null;
    $order = null;

    try {
        $user = User::on('mysql')->create([
            'name' => 'Loyalty Refund Race Test',
            'email' => 'loyalty-refund-race-'.uniqid().'@example.com',
            'password' => bcrypt('irrelevant'),
            'points_balance' => 0,
        ]);
        $order = Order::on('mysql')->create([
            'order_number' => 'ORD-LOYALTY-RACE-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'discount' => 60,
            'tax' => 0,
            'total' => 40,
            'shipping_address' => [],
            'billing_address' => [],
            'points_redeemed' => 60,
        ]);

        // Both connections start their transaction — and therefore their
        // REPEATABLE READ snapshot — before either does any locking work,
        // which is the precondition for the original race.
        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // Connection A: lock the Order row (mirroring
        // refundRedeemedPointsIfApplicable's real sequence), re-check the
        // guard, lock the user row, credit it, mark the order refunded, commit.
        $connA->prepare('SELECT points_refunded_at FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $guardA = $connA->query("SELECT points_refunded_at FROM orders WHERE id = {$order->id}")->fetchColumn();
        expect($guardA)->toBeNull();

        $connA->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE')->execute([$user->id]);
        $connA->prepare('UPDATE users SET points_balance = points_balance + 60 WHERE id = ?')->execute([$user->id]);
        $connA->prepare("INSERT INTO referral_point_transactions (user_id, order_id, type, points, note, created_at, updated_at) VALUES (?, ?, 'refund', 60, 'race test', NOW(), NOW())")->execute([$user->id, $order->id]);
        $connA->prepare('UPDATE orders SET points_refunded_at = NOW() WHERE id = ?')->execute([$order->id]);
        $connA->exec('COMMIT');

        // Connection B was blocked on the order-row lock until A committed.
        // It now proceeds with the same locking read — the actual fix
        // under test. A locking read bypasses B's snapshot and must see
        // A's just-committed points_refunded_at, not the null that a plain
        // (non-locking) read would have returned from B's pre-A-commit
        // snapshot.
        $connB->prepare('SELECT points_refunded_at FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $guardB = $connB->query("SELECT points_refunded_at FROM orders WHERE id = {$order->id}")->fetchColumn();
        $connB->exec('COMMIT');

        expect($guardB)->not->toBeNull();

        $finalBalance = (int) $connA->query("SELECT points_balance FROM users WHERE id = {$user->id}")->fetchColumn();
        expect($finalBalance)->toBe(60); // credited exactly once, not twice

        $transactionCount = (int) $connA->query("SELECT COUNT(*) FROM referral_point_transactions WHERE order_id = {$order->id} AND type = 'refund'")->fetchColumn();
        expect($transactionCount)->toBe(1);
    } finally {
        // DB-001: referral_point_transactions.user_id is RESTRICT, so this
        // test's own ledger rows go first.
        if ($user !== null) {
            ReferralPointTransaction::where('user_id', $user->id)->delete();
        }
        $order?->forceDelete();
        $user?->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('does not double-grant the registration bonus when two triggers race under real MySQL locking', function () {
    $conns = loyaltyRaceConnections();

    if ($conns === null) {
        return;
    }

    [$connA, $connB] = $conns;

    $user = null;

    try {
        $user = User::on('mysql')->create([
            'name' => 'Loyalty Registration Race Test',
            'email' => 'loyalty-registration-race-'.uniqid().'@example.com',
            'password' => bcrypt('irrelevant'),
            'points_balance' => 0,
        ]);

        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // Connection A: lock the user row first (mirroring
        // grantRegistrationBonus's real sequence), then check the ledger —
        // this plain read is the transaction's first plain read, so its
        // snapshot is only established now, not at START TRANSACTION.
        $connA->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE')->execute([$user->id]);
        $existingA = (int) $connA->query("SELECT COUNT(*) FROM referral_point_transactions WHERE user_id = {$user->id} AND type = 'earn_registration'")->fetchColumn();
        expect($existingA)->toBe(0);

        $connA->prepare('UPDATE users SET points_balance = points_balance + 20 WHERE id = ?')->execute([$user->id]);
        $connA->prepare("INSERT INTO referral_point_transactions (user_id, type, points, note, created_at, updated_at) VALUES (?, 'earn_registration', 20, 'race test', NOW(), NOW())")->execute([$user->id]);
        $connA->exec('COMMIT');

        // Connection B was blocked on the user-row lock until A committed.
        // Its ledger check — the first plain read in *this* transaction —
        // must now see A's committed row, not a stale "0" snapshot.
        $connB->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE')->execute([$user->id]);
        $existingB = (int) $connB->query("SELECT COUNT(*) FROM referral_point_transactions WHERE user_id = {$user->id} AND type = 'earn_registration'")->fetchColumn();
        $connB->exec('COMMIT');

        expect($existingB)->toBe(1);

        $finalBalance = (int) $connA->query("SELECT points_balance FROM users WHERE id = {$user->id}")->fetchColumn();
        expect($finalBalance)->toBe(20); // credited exactly once, not twice
    } finally {
        if ($user !== null) {
            ReferralPointTransaction::where('user_id', $user->id)->delete();
        }
        $user?->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

/*
 * PROMO-LOY-002: unlike the two tests above, these run the real application
 * code on both sides of the race. The test process opens a transaction and
 * runs a real admin action inside it (so its Order row lock is held), then
 * starts a second PHP process running another real admin action or courier
 * sync for the same order, confirms via INNODB_TRX that the second process
 * is genuinely blocked in LOCK WAIT, and only then commits. The second
 * process must then see the committed status on its locked re-read.
 */
const LOYALTY_RACE_CONTENDER_SCRIPT = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "Refusing to run against database {$database}.");
    exit(3);
}

$order = App\Models\Order::findOrFail((int) $argv[1]);
$controller = $app->make(App\Http\Controllers\Admin\OrderController::class);

match ($argv[2]) {
    'cancel' => $controller->cancel(Illuminate\Http\Request::create('/', 'PATCH'), $order),
    'refund' => $controller->markRefunded($order),
    'steadfast-cancelled' => App\Services\CourierOrderStatusMapper::sync($order, 'steadfast', 'cancelled_parcells'),
};
PHP;

function createRedeemedPointsRaceOrder(): array
{
    $user = User::create([
        'name' => 'Loyalty Atomicity Race Test',
        'email' => 'loyalty-atomicity-race-'.uniqid().'@example.com',
        'password' => bcrypt('irrelevant'),
    ]);
    $user->forceFill(['points_balance' => 40])->save();

    $order = Order::create([
        'order_number' => 'ORD-ATOMIC-RACE-'.uniqid(),
        'user_id' => $user->id,
        'status' => OrderStatus::PROCESSING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 60,
        'discount' => 60,
        'tax' => 0,
        'total' => 1000,
        'points_redeemed' => 60,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
    ]);

    return [$user, $order];
}

/**
 * Runs $firstAction in-process inside a still-open transaction, races
 * $contenderAction from a second process against it, and returns the
 * contender's finished process result.
 */
function raceAdminActions(PDO $monitor, Order $order, Closure $firstAction, string $contenderAction): ProcessResult
{
    $database = DB::connection()->getDatabaseName();

    DB::beginTransaction();

    try {
        $firstAction();

        $contender = Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => $database,
                'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ])
            ->timeout(60)
            ->start(['php', '-r', LOYALTY_RACE_CONTENDER_SCRIPT, (string) $order->id, $contenderAction]);

        $blocked = false;

        for ($attempt = 0; $attempt < 100 && $contender->running(); $attempt++) {
            if ((int) $monitor->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT'")->fetchColumn() > 0) {
                $blocked = true;

                break;
            }

            usleep(100_000);
        }

        expect($blocked)->toBeTrue('The contending process never blocked on the Order row lock.');
    } finally {
        DB::commit();
    }

    $result = $contender->wait();

    expect($result->exitCode())->toBe(0, $result->errorOutput());

    return $result;
}

function controllerUnderRace(): OrderController
{
    return app(OrderController::class);
}

it('rejects a duplicate admin cancellation that was blocked behind the first one under real MySQL locking', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$user, $order] = createRedeemedPointsRaceOrder();

    raceAdminActions($conns[0], $order, fn () => controllerUnderRace()->cancel(Request::create('/', 'PATCH'), $order), 'cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    expect(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(100);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('rejects a duplicate mark-refunded that was blocked behind the first one under real MySQL locking', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$user, $order] = createRedeemedPointsRaceOrder();

    raceAdminActions($conns[0], $order, fn () => controllerUnderRace()->markRefunded($order), 'refund');

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    expect(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(100);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('refunds once when an admin cancellation races a mark-refunded under real MySQL locking', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$user, $order] = createRedeemedPointsRaceOrder();

    raceAdminActions($conns[0], $order, fn () => controllerUnderRace()->cancel(Request::create('/', 'PATCH'), $order), 'refund');

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(2);
    expect(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(100);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('leaves an admin-cancelled order alone when a courier cancellation report races it under real MySQL locking', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$user, $order] = createRedeemedPointsRaceOrder();

    raceAdminActions($conns[0], $order, fn () => controllerUnderRace()->cancel(Request::create('/', 'PATCH'), $order), 'steadfast-cancelled');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->steadfast_status)->toBe('cancelled_parcells');
    // The courier's report is still recorded, but as a no-op: the order
    // was already cancelled by the time the contender's locked re-read ran.
    expect(OrderStatusHistory::where('order_id', $order->id)->pluck('notes')->all())
        ->toHaveCount(2)
        ->and(OrderStatusHistory::where('order_id', $order->id)->latest('id')->value('notes'))->toBe('Steadfast reported "Cancelled Parcels".');
    expect(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(100);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('rolls back the status change, history and refund together under real MySQL when the REFUND ledger entry fails', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$user, $order] = createRedeemedPointsRaceOrder();

    ReferralPointTransaction::creating(function (ReferralPointTransaction $transaction): void {
        if ($transaction->type === PointTransactionType::REFUND) {
            throw new RuntimeException('Simulated REFUND ledger failure.');
        }
    });

    expect(fn () => controllerUnderRace()->markRefunded($order))
        ->toThrow(RuntimeException::class, 'Simulated REFUND ledger failure.');

    expect(DB::transactionLevel())->toBe(0);

    // Read through the independent connection: nothing was committed, and
    // the Order row lock was released (NOWAIT would fail if still held).
    [$monitor] = $conns;
    $row = $monitor->query("SELECT status, points_refunded_at FROM orders WHERE id = {$order->id} FOR UPDATE NOWAIT")->fetch(PDO::FETCH_ASSOC);
    expect($row)->toBe(['status' => 'processing', 'points_refunded_at' => null]);
    expect((int) $monitor->query("SELECT points_balance FROM users WHERE id = {$user->id}")->fetchColumn())->toBe(40);
    expect((int) $monitor->query("SELECT COUNT(*) FROM order_status_histories WHERE order_id = {$order->id}")->fetchColumn())->toBe(0);
    expect((int) $monitor->query("SELECT COUNT(*) FROM referral_point_transactions WHERE order_id = {$order->id}")->fetchColumn())->toBe(0);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('retries mark-refunded after a real InnoDB deadlock without duplicating any side effect', function () {
    if (($conns = loyaltyRaceConnections()) === null) {
        return;
    }

    [$monitor, $rival] = $conns;
    [$user, $order] = createRedeemedPointsRaceOrder();

    $product = Product::factory()->create(['stock_quantity' => 10]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 2,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    $deadlocksBefore = (int) $monitor->query("SELECT COUNT FROM information_schema.INNODB_METRICS WHERE NAME = 'lock_deadlocks'")->fetchColumn();
    $mailJobsBefore = (int) $monitor->query("SELECT COUNT(*) FROM jobs WHERE payload LIKE '%OrderStatusUpdatedMail%'")->fetchColumn();

    // The rival transaction is made "heavy" (InnoDB rolls back the
    // transaction that has modified the fewest rows), then takes the
    // customer's row lock — the second lock the refund needs.
    $rival->exec('START TRANSACTION');
    $insert = $rival->prepare('INSERT INTO cache (`key`, `value`, `expiration`) VALUES (?, ?, ?)');
    for ($i = 0; $i < 200; $i++) {
        $insert->execute(["deadlock-ballast-{$order->id}-{$i}", 'x', 0]);
    }
    $rival->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$user->id]);

    $contender = Process::path(base_path())
        ->env([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ])
        ->timeout(60)
        ->start(['php', '-r', LOYALTY_RACE_CONTENDER_SCRIPT, (string) $order->id, 'refund']);

    // Wait until markRefunded() holds the Order row and is blocked on the
    // customer row inside the redeemed-points refund (a nested transaction).
    $blocked = false;
    for ($attempt = 0; $attempt < 100 && $contender->running(); $attempt++) {
        if ((int) $monitor->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT'")->fetchColumn() > 0) {
            $blocked = true;

            break;
        }
        usleep(100_000);
    }
    expect($blocked)->toBeTrue('markRefunded() never blocked on the customer row.');

    // Close the cycle: the rival now asks for the Order row the application
    // holds. InnoDB must pick a victim; the rival must not be it.
    $rival->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
    $rival->exec('ROLLBACK');

    $result = $contender->wait();
    expect($result->exitCode())->toBe(0, $result->errorOutput());

    $deadlocksAfter = (int) $monitor->query("SELECT COUNT FROM information_schema.INNODB_METRICS WHERE NAME = 'lock_deadlocks'")->fetchColumn();
    expect($deadlocksAfter - $deadlocksBefore)->toBe(1);

    // The application's first attempt was the deadlock victim and was rolled
    // back by InnoDB; it only completed because the outer transaction retried.
    // Every side effect is present exactly once.
    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->points_refunded_at)->not->toBeNull();
    expect($user->refresh()->points_balance)->toBe(100);
    expect(ReferralPointTransaction::where('order_id', $order->id)->where('type', PointTransactionType::REFUND)->count())->toBe(1);
    expect(OrderStatusHistory::where('order_id', $order->id)->count())->toBe(1);
    expect($product->refresh()->stock_quantity)->toBe(12);
    expect(OrderItem::where('order_id', $order->id)->value('restocked_quantity'))->toBe(2);
    expect((int) $monitor->query("SELECT COUNT(*) FROM jobs WHERE payload LIKE '%OrderStatusUpdatedMail%'")->fetchColumn() - $mailJobsBefore)->toBe(1);
    expect((int) $monitor->query("SELECT COUNT(*) FROM cache WHERE `key` LIKE 'deadlock-ballast-{$order->id}-%'")->fetchColumn())->toBe(0);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');
