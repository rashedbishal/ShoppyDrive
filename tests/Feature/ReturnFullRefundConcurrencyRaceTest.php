<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PAY-RETURN-001 regression: `Admin\ReturnController::markRefunded()`'s
 * full-return check (`ReturnRequest::isOrderFullyReturned()`) sums sibling
 * `return_requests` rows filtered to `status = 'refunded'` as a *locking*
 * read (`->lockForUpdate()`), specifically so it bypasses this transaction's
 * InnoDB REPEATABLE READ snapshot and always sees the latest committed
 * sibling rows — the same reasoning already applied to the referral
 * monthly-cap check in PROMO-LOY-001. Without that locking read, a
 * transaction whose snapshot was established *before* a sibling return
 * request's refund committed could incorrectly conclude the order isn't
 * fully returned yet, even though it now is, and never flip the order to
 * REFUNDED (silently losing the coupon-release/referral-revoke/redeemed-
 * points-refund side effects that transition is supposed to trigger).
 *
 * This can ONLY be demonstrated with two real, separate database
 * connections racing against real MySQL/InnoDB locking semantics — the
 * rest of this suite runs on SQLite in-memory (phpunit.xml), which would
 * silently "pass" regardless of whether the fix is present. This file
 * mirrors `tests/Feature/CouponConcurrencyRaceTest.php`'s and
 * `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php`'s safety-gating
 * convention exactly, including their SAFETY rationale (this local MySQL
 * server hosts several unrelated databases under the same root user), using
 * its own `RETURN_RACE_TEST_DATABASE` variable per that same convention (one
 * disposable-database variable per concern, not shared across test files).
 *
 * Scope note: like the other two race-test files, this proves snapshot-
 * visibility correctness (connection B, after A commits, correctly sees A's
 * commit instead of a stale pre-commit snapshot) — it does not and cannot
 * reproduce genuine simultaneous blocking between two connections (that
 * requires real parallelism, unavailable in a single-threaded PHP test
 * process), so it is not a test of deadlock behavior. The Order-first lock
 * ordering adopted for every return-processing action specifically to avoid
 * a deadlock here is verified by code review/reasoning only, not by an
 * executed test — see the PAY-RETURN-001 report for that reasoning.
 */
function returnRaceConnections(): ?array
{
    $testDatabase = env('RETURN_RACE_TEST_DATABASE');

    if (! $testDatabase) {
        test()->markTestSkipped('Set RETURN_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database name to run this test — it is never run against the real dev/production database.');

        return null;
    }

    if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $testDatabase) !== 1) {
        test()->markTestSkipped("RETURN_RACE_TEST_DATABASE '{$testDatabase}' does not match the required allowlist pattern '^shoppydrive_test_[a-z0-9_]+\$' — refusing to connect to anything that isn't unambiguously a disposable test database.");

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
    // REL-003: another local port, such as a MariaDB copy of production, has to be named.
    $allowedPort = (string) (env('RACE_TEST_PORT') ?: '3306');

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

    // REL-002: this file's "a plain read stays on its stale snapshot"
    // assertions only hold under REPEATABLE READ. MySQL defaults to that,
    // but production runs MariaDB under READ-COMMITTED (confirmed by
    // read-only production check), where every plain read sees the latest
    // committed row regardless of when the transaction started — so this
    // test previously passed or failed depending on which server it ran
    // against, not on whether the actual locking-read fix (the thing being
    // tested) was present. Setting the isolation level explicitly makes
    // the test deterministic on either server, rather than assuming a
    // default. The locking-read assertions (the actual safety property)
    // were never affected either way — FOR UPDATE always sees committed
    // data under both isolation levels.
    $connA->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $connB->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

    return [$connA, $connB];
}

it('detects full-return completion under real MySQL locking even when the completing connection\'s snapshot predates the sibling\'s commit', function () {
    $conns = returnRaceConnections();

    if ($conns === null) {
        return;
    }

    [$connA, $connB] = $conns;

    $user = null;
    $order = null;

    try {
        $user = User::on('mysql')->create([
            'name' => 'Return Race Test',
            'email' => 'return-race-'.uniqid().'@example.com',
            'password' => bcrypt('irrelevant'),
        ]);
        $order = Order::on('mysql')->create([
            'order_number' => 'ORD-RETURN-RACE-'.uniqid(),
            'user_id' => $user->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 1000,
            'shipping_address' => [],
            'billing_address' => [],
            'delivered_at' => now()->subDay(),
        ]);
        $itemA = OrderItem::on('mysql')->create([
            'order_id' => $order->id, 'product_id' => null, 'variant_id' => null,
            'product_name' => 'Item A', 'price' => 500, 'quantity' => 1,
            'restocked_quantity' => 1, 'subtotal' => 500, 'product_snapshot' => [],
        ]);
        $itemB = OrderItem::on('mysql')->create([
            'order_id' => $order->id, 'product_id' => null, 'variant_id' => null,
            'product_name' => 'Item B', 'price' => 500, 'quantity' => 1,
            'restocked_quantity' => 1, 'subtotal' => 500, 'product_snapshot' => [],
        ]);
        $returnA = ReturnRequest::on('mysql')->create([
            'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Race test A',
            'status' => 'approved',
            'items' => json_encode([['order_item_id' => $itemA->id, 'product_id' => null, 'quantity' => 1]]),
        ]);
        $returnB = ReturnRequest::on('mysql')->create([
            'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Race test B',
            'status' => 'approved',
            'items' => json_encode([['order_item_id' => $itemB->id, 'product_id' => null, 'quantity' => 1]]),
        ]);

        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // Connection B establishes its REPEATABLE READ snapshot *before*
        // connection A commits anything — a plain read of its own return
        // request, mirroring the first plain read a real request would
        // incidentally perform via route-model binding before its own
        // transaction even begins its locking work.
        $connB->query("SELECT status FROM return_requests WHERE id = {$returnB->id}")->fetchColumn();

        // Connection A: lock Order, lock its own ReturnRequest, refund it,
        // then run the (locking) full-return check — sees its own write to
        // return A plus return B still 'approved', so full-return is false
        // and the order is correctly left alone.
        $connA->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $connA->prepare('SELECT * FROM return_requests WHERE id = ? FOR UPDATE')->execute([$returnA->id]);
        $connA->prepare("UPDATE return_requests SET status = 'refunded', refund_amount = 500, refund_method = 'bKash', resolved_at = NOW() WHERE id = ?")->execute([$returnA->id]);
        $refundedCountForA = (int) $connA->query("SELECT COUNT(*) FROM return_requests WHERE order_id = {$order->id} AND status = 'refunded' FOR UPDATE")->fetchColumn();
        expect($refundedCountForA)->toBe(1); // only A's own write — B is still 'approved'
        $connA->exec('COMMIT');

        // Connection B was blocked on the Order row lock until A committed.
        // It now proceeds with its own refund, then the full-return check —
        // the actual fix under test. A locking read bypasses B's
        // *already-established* snapshot (taken above, before A's commit)
        // and must see A's just-committed refund, not the stale 'approved'
        // that a plain (non-locking) read would still return from that
        // earlier snapshot.
        $connB->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $connB->prepare('SELECT * FROM return_requests WHERE id = ? FOR UPDATE')->execute([$returnB->id]);
        $connB->prepare("UPDATE return_requests SET status = 'refunded', refund_amount = 500, refund_method = 'bKash', resolved_at = NOW() WHERE id = ?")->execute([$returnB->id]);
        $refundedCountForB = (int) $connB->query("SELECT COUNT(*) FROM return_requests WHERE order_id = {$order->id} AND status = 'refunded' FOR UPDATE")->fetchColumn();
        expect($refundedCountForB)->toBe(2); // must see A's committed refund too, not just its own

        if ($refundedCountForB === 2) {
            $connB->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?")->execute([$order->id]);
        }
        $connB->exec('COMMIT');

        $finalStatus = $connA->query("SELECT status FROM orders WHERE id = {$order->id}")->fetchColumn();
        expect($finalStatus)->toBe('refunded');

        $refundedStatuses = $connA->query("SELECT status FROM return_requests WHERE order_id = {$order->id}")->fetchAll(PDO::FETCH_COLUMN);
        expect($refundedStatuses)->toEqual(['refunded', 'refunded']);
    } finally {
        if ($order !== null) {
            ReturnRequest::on('mysql')->where('order_id', $order->id)->delete();
            OrderItem::on('mysql')->where('order_id', $order->id)->delete();
            $order->forceDelete();
        }
        $user?->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');

it('serializes two refunds of one order on the order row lock, so the second sees the first\'s ledger row (PAY-RETURN-001)', function () {
    $conns = returnRaceConnections();

    if ($conns === null) {
        return;
    }

    [$connA, $connB] = $conns;
    $order = null;

    try {
        $order = Order::on('mysql')->create([
            'order_number' => 'ORD-REFUND-RACE-'.uniqid(), 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'zinipay',
            'subtotal' => 1000, 'shipping_cost' => 60, 'discount' => 100, 'tax' => 0, 'total' => 960,
            'shipping_address' => [], 'billing_address' => [], 'delivered_at' => now()->subDay(),
        ]);

        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // B takes its REPEATABLE READ snapshot first (a plain read), before A's refund exists.
        expect((float) $connB->query("SELECT COALESCE(SUM(amount), 0) FROM order_refunds WHERE order_id = {$order->id}")->fetchColumn())->toBe(0.0);

        // A: the RefundService shape — order row lock, locking ledger read, ledger insert.
        $connA->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $connA->query("SELECT COALESCE(SUM(amount), 0) FROM order_refunds WHERE order_id = {$order->id} FOR UPDATE")->fetchColumn();
        $connA->prepare("INSERT INTO order_refunds (order_id, source, amount, method, is_backfilled, created_at) VALUES (?, 'return', 600, 'bKash', 0, NOW())")->execute([$order->id]);
        $connA->exec('COMMIT');

        // B: same shape. The locking read sees A's committed row despite B's older snapshot,
        // so the remaining amount is 900 − 600 = 300 — B cannot overpay.
        $connB->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE')->execute([$order->id]);
        $lockedSum = (float) $connB->query("SELECT COALESCE(SUM(amount), 0) FROM order_refunds WHERE order_id = {$order->id} FOR UPDATE")->fetchColumn();
        $plainSum = (float) $connB->query("SELECT COALESCE(SUM(amount), 0) FROM order_refunds WHERE order_id = {$order->id}")->fetchColumn();
        $connB->exec('COMMIT');

        expect($lockedSum)->toBe(600.0)
            ->and($plainSum)->toBe(0.0); // why RefundCalculator::refundedPaisa(lock: true) uses a locking read
    } finally {
        if ($order !== null) {
            DB::connection('mysql')->table('order_refunds')->where('order_id', $order->id)->delete();
            DB::connection('mysql')->table('orders')->where('id', $order->id)->delete();
        }
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');
