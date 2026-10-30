<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-001: deleting a row must never silently delete order, money,
     * loyalty, coupon or audit history through a cascading foreign key.
     *
     * RESTRICT — the database refuses the delete while history exists:
     *   orders.user_id, payments.order_id, return_requests.{order_id,user_id},
     *   coupon_uses.{order_id,user_id,coupon_id}, referral_point_transactions.user_id,
     *   users.referral_discount_used_order_id (a consumed referral discount),
     *   users.referred_by_id (other customers' referral link to this one).
     * SET NULL — the history row stays, only the link is cleared:
     *   orders.coupon_id (the order keeps coupon_code and discount),
     *   order_status_histories.created_by, inventory_logs.created_by,
     *   product_questions.answered_by.
     *
     * Each change drops and re-adds one foreign key. MySQL/MariaDB commit
     * every DDL statement, so a failure part-way leaves some keys changed —
     * see docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md §7.
     *
     * Each entry: [table, column, referenced table, new rule, previous rule].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const CHANGES = [
        ['orders', 'user_id', 'users', 'restrict', 'cascade'],
        ['orders', 'coupon_id', 'coupons', 'set null', 'cascade'],
        ['payments', 'order_id', 'orders', 'restrict', 'cascade'],
        ['return_requests', 'order_id', 'orders', 'restrict', 'cascade'],
        ['return_requests', 'user_id', 'users', 'restrict', 'cascade'],
        ['coupon_uses', 'order_id', 'orders', 'restrict', 'cascade'],
        ['coupon_uses', 'user_id', 'users', 'restrict', 'cascade'],
        ['coupon_uses', 'coupon_id', 'coupons', 'restrict', 'cascade'],
        ['referral_point_transactions', 'user_id', 'users', 'restrict', 'cascade'],
        ['users', 'referral_discount_used_order_id', 'orders', 'restrict', 'set null'],
        ['users', 'referred_by_id', 'users', 'restrict', 'set null'],
        ['order_status_histories', 'created_by', 'users', 'set null', 'cascade'],
        ['inventory_logs', 'created_by', 'users', 'set null', 'cascade'],
        ['product_questions', 'answered_by', 'users', 'set null', 'cascade'],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as [$table, $column, $references, $rule]) {
            $this->replaceForeignKey($table, $column, $references, $rule);
        }
    }

    /**
     * Restoring the old cascades would re-open the paths that silently
     * destroy order and money history, so this only runs on a database
     * that holds no orders or customers (e.g. a fresh development copy).
     */
    public function down(): void
    {
        if (DB::table('orders')->exists() || DB::table('users')->exists()) {
            throw new RuntimeException('Refusing to roll back: restoring the cascading foreign keys would let deleting a customer or coupon silently delete order, payment and loyalty history. Roll back only on an empty database, or write a deliberate, reviewed migration.');
        }

        foreach (array_reverse(self::CHANGES) as [$table, $column, $references, , $previousRule]) {
            $this->replaceForeignKey($table, $column, $references, $previousRule);
        }
    }

    private function replaceForeignKey(string $table, string $column, string $references, string $rule): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $rule) {
            $blueprint->dropForeign([$column]);
            $blueprint->foreign($column)->references('id')->on($references)->onDelete($rule);
        });
    }
};
