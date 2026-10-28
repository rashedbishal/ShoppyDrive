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
 * SAFETY — this test must never be able to reach any database it wasn't
 * deliberately, narrowly pointed at, given this local MySQL server hosts
 * several unrelated databases under the same root user (confirmed via
 * SHOW DATABASES in an earlier audit session: ABL_ORG, ERP_OSL,
 * HOTEL_JANA, OSL_JANA_PORTAL, TEST_PORTAL, ShoppyDrive, etc.). Two
 * independent, explicit gates must both pass before any connection is
 * attempted:
 *   1. COUPON_RACE_TEST_DATABASE must be set AND match
 *      ^shoppydrive_test_[a-z0-9_]+$ — rejects both "unset" and "set to
 *      something that isn't obviously a disposable test database" (e.g.
 *      a typo'd "ShoppyDrive" or any other real project's database name).
 *   2. The resolved host/port (whatever the 'mysql' connection config
 *      currently points at) must be in a small, explicit local-testing
 *      allowlist — this test only overrides the database *name*, so
 *      without this check it would otherwise silently trust whatever
 *      host .env happens to configure, including a future remote one.
 * phpunit.xml globally overrides DB_DATABASE to ':memory:' for the whole
 * process (shared by both the sqlite and mysql connection blocks in
 * config/database.php), which is why the database name can't be read
 * from env('DB_DATABASE') here and must come from the dedicated
 * COUPON_RACE_TEST_DATABASE variable instead.
 */
it('serializes concurrent per-user coupon redemption under real MySQL locking instead of racing', function () {
    $testDatabase = env('COUPON_RACE_TEST_DATABASE');

    if (! $testDatabase) {
        test()->markTestSkipped('Set COUPON_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database name to run this test — it is never run against the real dev/production database.');

        return;
    }

    if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $testDatabase) !== 1) {
        test()->markTestSkipped("COUPON_RACE_TEST_DATABASE '{$testDatabase}' does not match the required allowlist pattern '^shoppydrive_test_[a-z0-9_]+\$' — refusing to connect to anything that isn't unambiguously a disposable test database.");

        return;
    }

    config(['database.connections.mysql.database' => $testDatabase]);
    DB::purge('mysql');

    $params = config('database.connections.mysql');

    // Explicit local-testing allowlist for the host/port this would
    // actually connect to — the check above only constrains the
    // database *name*; without this, a future remote DB_HOST in .env
    // would be trusted silently.
    $allowedHosts = ['127.0.0.1', 'localhost'];
    $allowedPort = '3306';

    if (! in_array($params['host'], $allowedHosts, true) || (string) $params['port'] !== $allowedPort) {
        test()->markTestSkipped("Resolved MySQL host/port ({$params['host']}:{$params['port']}) is not in the explicit local-testing allowlist (host: 127.0.0.1 or localhost, port: {$allowedPort}) — refusing to connect.");

        return;
    }

    $dsn = "mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4";

    // Declared before the outer try so the finally block below can
    // safely check each one regardless of how far setup got before a
    // failure — a partial-setup failure (e.g. a schema mismatch on a
    // freshly-provisioned database) must still clean up whatever was
    // already created, not just whatever existed at the point of the
    // original try/finally's opening brace.
    $connA = null;
    $connB = null;
    $user = null;
    $coupon = null;
    $order = null;

    try {
        try {
            $connA = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connB = new PDO($dsn, $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            test()->markTestSkipped('No reachable local MySQL connection to run a real concurrency test against: '.$e->getMessage());

            return;
        }

        // Created via Eloquent on the (now correctly pointed) 'mysql'
        // connection so every required column (password hash,
        // referral_code, timestamps, etc.) is populated exactly as the
        // real app would — deliberately not hand-written raw INSERTs,
        // which would have to duplicate that logic and risk violating a
        // NOT NULL constraint.
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
        // SHOW CREATE TABLE) — a placeholder like 0 would violate it on
        // real MySQL, so a minimal throwaway order is required too.
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

        // Both connections start their transaction — and therefore their
        // REPEATABLE READ snapshot — before either does any locking
        // work, which is the precondition for the original race.
        $connA->exec('START TRANSACTION');
        $connB->exec('START TRANSACTION');

        // Connection A: lock the coupon row, lock-read the per-user
        // count (0, correctly), insert a CouponUse, then commit —
        // mirroring CouponService::consume()'s real sequence.
        $connA->prepare('SELECT * FROM coupons WHERE id = ? FOR UPDATE')->execute([$coupon->id]);
        $countStmtA = $connA->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND user_id = ? FOR UPDATE');
        $countStmtA->execute([$coupon->id, $user->id]);
        expect((int) $countStmtA->fetchColumn())->toBe(0);

        $connA->prepare('INSERT INTO coupon_uses (coupon_id, user_id, order_id, discount_amount, created_at, updated_at) VALUES (?, ?, ?, 10, NOW(), NOW())')
            ->execute([$coupon->id, $user->id, $order->id]);
        $connA->exec('COMMIT');

        // Connection B was blocked on the coupon row lock until A
        // committed. It now proceeds with the SAME locking read — this
        // is the actual fix under test. A locking read bypasses B's
        // snapshot and must see A's just-committed row, not the "0" that
        // a plain (non-locking) read would have returned from B's
        // original pre-A-commit snapshot.
        $connB->prepare('SELECT * FROM coupons WHERE id = ? FOR UPDATE')->execute([$coupon->id]);
        $countStmtB = $connB->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = ? AND user_id = ? FOR UPDATE');
        $countStmtB->execute([$coupon->id, $user->id]);
        $countSeenByB = (int) $countStmtB->fetchColumn();
        $connB->exec('COMMIT');

        expect($countSeenByB)->toBe(1);

        $finalCount = (int) $connA->query("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id = {$coupon->id} AND user_id = {$user->id}")->fetchColumn();
        expect($finalCount)->toBe(1);
    } finally {
        // Every step below is null-safe and independently guarded, so
        // partial setup (e.g. $user created but $coupon's create() then
        // threw) still cleans up exactly what exists rather than either
        // skipping cleanup entirely or erroring on a null reference.
        // forceDelete(), not delete(), for User/Order — both use
        // SoftDeletes, so a plain delete() would leave a permanent
        // soft-deleted row behind in whatever database this ran against
        // on every single run.
        if ($coupon) {
            DB::connection('mysql')->table('coupon_uses')->where('coupon_id', $coupon->id)->delete();
        }
        $order?->forceDelete();
        $coupon?->delete();
        $user?->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires a real MySQL connection to exercise InnoDB locking-read semantics');
