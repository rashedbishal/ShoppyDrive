<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COURIER-SEND-RACE-002 (M3): permanently deleting an order no longer
     * deletes its courier attempt and reconciliation history. The link is
     * cleared instead (`order_id` → NULL); `reference` (the order number),
     * the consignment IDs and the resolution notes stay.
     *
     * Not RESTRICT on purpose: orders are also deleted by cascade when a
     * customer deletes their own account (`orders.user_id` cascades), and a
     * restrictive key would make that account deletion fail outright.
     */
    public function up(): void
    {
        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    /**
     * Reverting would restore cascade deletion, so it refuses — before any
     * schema change — while the table holds genuine operational history
     * (same definition as the create-table rollback guard: any row not
     * created by the legacy backfill). Otherwise a batch rollback could put
     * the cascade back and then stop at a later guard, leaving real history
     * exposed to deletion. It also refuses while any row is detached (its
     * order deleted), which NOT NULL could not hold. With only attached
     * backfilled rows left, the rollback proceeds as before.
     */
    public function down(): void
    {
        if (DB::table('courier_submissions')->where('source', '!=', 'legacy_backfill')->exists()) {
            throw new RuntimeException('Refusing to roll back: courier_submissions contains real courier send attempts and reconciliation records (source != legacy_backfill); restoring the cascading key would let order deletion destroy them. Export them first and remove this guard deliberately if the rollback is really intended.');
        }

        if (DB::table('courier_submissions')->whereNull('order_id')->exists()) {
            throw new RuntimeException('Refusing to roll back: some courier_submissions rows belong to deleted orders (order_id is NULL) and would have to be destroyed to restore the cascading NOT NULL key.');
        }

        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
