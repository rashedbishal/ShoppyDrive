<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-001 (L2): deleting a coupon in the admin is a soft delete, so its
     * row — and therefore every order and coupon use that references it —
     * stays. See also protect_order_history_foreign_keys.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Dropping the column would silently "undelete" every soft-deleted
     * coupon, so it refuses while any exist.
     */
    public function down(): void
    {
        if (DB::table('coupons')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Refusing to roll back: some coupons are soft-deleted; dropping deleted_at would make them active again.');
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
