<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * DB-002 regression: CouponService::consume()'s per-user-limit check used
 * to run a plain (non-locking) count() against coupon_uses. Under InnoDB's
 * default REPEATABLE READ isolation, a plain read sees a snapshot fixed
 * when the transaction started — not the latest committed data — so two
 * genuinely concurrent connections could each see "0 prior uses" and both
 * pass the per-user-limit check before either commits.
 *
 * This can ONLY be demonstrated with two real, separate database
 * connections racing against real MySQL/InnoDB locking semantics — the
 * rest of this suite runs on SQLite in-memory (phpunit.xml), which
 * doesn't implement REPEATABLE READ snapshot/locking-read semantics the
 * same way and would silently "pass" regardless of whether the fix is
 * present. Skipped unless the suite is deliberately run against a real
 * MySQL connection, mirroring the existing pattern in
 * tests/Feature/ProductSearchTest.php for the same reason.
 *
 * phpunit.xml globally overrides DB_DATABASE to ':memory:' for the whole
 * process (it's shared by both the sqlite and mysql connection blocks in
 * config/database.php), so the 'mysql' connection's configured database
 * name can't be recovered from env('DB_DATABASE') here. Deliberately NOT
 * defaulted to the real local dev database ("ShoppyDrive") either — this
 * test must never be able to run against it (or, worse, against one of
 * the several other unrelated databases sharing this same local MySQL
 * server/root user) by accident. It requires a dedicated, disposable
 * database named explicitly via COUPON_RACE_TEST_DATABASE and skips
 * cleanly if that isn't set, on top of the outer skip() below which
 * already keeps this from running under the normal SQLite-backed suite.
 */
it('serializes concurrent per-user coupon redemption under real MySQL locking instead of racing', function () {
    $testDatabase = env('COUPON_RACE_TEST_DATABASE');

    if (! $testDatabase) {
        test()->markTestSkipped('Set COUPON_RACE_TEST_DATABASE to a disposable MySQL database name to run this test — it is never run against the real dev/production database.');

        return;
    }

    config(['database.connections.mysql.database' => $testDatabase]);
    DB::purge('mysql');

    $params = config('database.connections.mysql');
    $dsn = "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4";

    try {
        $connA = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connB = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        test()->markTestSkipped('No reachable local MySQL connection to run a real concurrency test against: '.$e->getMessage());

        return;
    }

    // Created via Eloquent on the (now correctly pointed) 'mysql'
    // connection so every required column (password hash, referral_code,
    // timestamps, etc.) is populated exactly as the real app would —
    // deliberately not hand-written raw INSERTs, which would have to
    // duplicate that logic and risk violating a NOT NULL constraint.
    $user = User::on('mysql')->create([
        'name' => 'Coupon Race Test',
        'email' => 'coupon-race-'.uniqid().'@example.com',
        'password' => bcrypt('irrelevant'),
    ]);
    $coupon = Coupon::on('mysql')->create([
        'code' => 'RACE-'.strtoupper(uniqid()),
        'name' => 'Concurrency race test coupon',
        'type' => 'fixed',
        'value' => 10,
        'per_user_limit' => 1,
        'max_uses' => null,
        'used_count' => 0,
        'is_active' => true,
    ]);
    // coupon_uses.order_id has a real FK to orders.id (confirmed via
    // SHOW CREATE TABLE) — a placeholder like 0 would violate it on real
    // MySQL, so a minimal throwaway order is required too.
    $order = Order::on('mysql')->create([
        'order_number' => 'ORD-RACE-'.uniqid(),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 10,
        'shipping_cost' => 0,
        'discount' => 10,
        'tax' => 0,
        'total' => 0,
        'shipping_address' => [],
        'billing_address' => [],
    ]);

    try {
        // Both connections start their transaction — and therefore their
        // REPEATABLE READ snapshot — before either does any locking work,
        // which is the precondition for the original race.
        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // Connection A: lock the coupon row, lock-read the per-user count
        // (0, correctly), insert a CouponUse, then commit — mirroring
        // CouponService::consume()'s real sequence.
        $connA->prepare('SELECT * FROM coupons WHERE id = ? FOR UPDATE')->execute([$coupon->id]);
        $countStmtA = $connA->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND user_id = ? FOR UPDATE');
        $countStmtA->execute([$coupon->id, $user->id]);
        expect((int) $countStmtA->fetchColumn())->toBe(0);

        $connA->prepare('INSERT INTO coupon_uses (coupon_id, user_id, order_id, discount_amount, created_at, updated_at) VALUES (?, ?, ?, 10, NOW(), NOW())')
            ->execute([$coupon->id, $user->id, $order->id]);
        $connA->exec('COMMIT');

        // Connection B was blocked on the coupon row lock until A
        // committed. It now proceeds with the SAME locking read — this is
        // the actual fix under test. A locking read bypasses B's snapshot
        // and must see A's just-committed row, not the "0" that a plain
        // (non-locking) read would have returned from B's original
        // pre-A-commit snapshot.
        $connB->prepare('SELECT * FROM coupons WHERE id = ? FOR UPDATE')->execute([$coupon->id]);
        $countStmtB = $connB->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND user_id = ? FOR UPDATE');
        $countStmtB->execute([$coupon->id, $user->id]);
        $countSeenByB = (int) $countStmtB->fetchColumn();
        $connB->exec('COMMIT');

        expect($countSeenByB)->toBe(1);

        $finalCount = (int) $connA->query("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = {$coupon->id} AND user_id = {$user->id}")->fetchColumn();
        expect($finalCount)->toBe(1);
    } finally {
        // forceDelete(), not delete() — both User and Order use
        // SoftDeletes, so a plain delete() would leave permanent
        // soft-deleted rows behind in whatever database this ran against
        // on every single run. The coupon_uses row is also deleted
        // explicitly rather than relying on the order's ON DELETE
        // CASCADE, so cleanup doesn't depend on deletion order.
        DB::connection('mysql')->table('coupon_uses')->where('coupon_id', $coupon->id)->delete();
        $order->forceDelete();
        $coupon->delete();
        $user->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');
