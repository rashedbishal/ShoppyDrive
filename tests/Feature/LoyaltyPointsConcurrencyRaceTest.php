<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        $user?->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');
