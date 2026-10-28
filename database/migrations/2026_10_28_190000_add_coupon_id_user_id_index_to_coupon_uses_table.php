<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CouponService::consume() now runs a locking read
     * (`->lockForUpdate()`) over `coupon_uses` filtered by
     * `(coupon_id, user_id)` to close a per-user-limit race condition
     * (see DB-002). Without a composite index on exactly those two
     * columns, that locking read falls back to one of the existing
     * single-column indexes and locks a wider row/gap range than it
     * needs to — this index narrows the lock to just the matching
     * coupon+user combination. Deliberately NOT unique: `per_user_limit`
     * is a configurable integer that can exceed 1, so a unique
     * constraint here would incorrectly cap every coupon at one use per
     * user regardless of its configured limit.
     */
    public function up(): void
    {
        Schema::table('coupon_uses', function (Blueprint $table) {
            $table->index(['coupon_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_uses', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'user_id']);
        });
    }
};
