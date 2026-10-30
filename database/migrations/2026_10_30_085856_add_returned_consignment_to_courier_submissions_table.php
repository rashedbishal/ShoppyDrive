<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COURIER-SEND-RACE-002 (H1): what the courier itself returned for an
     * attempt, saved the moment the response arrives — before the order is
     * updated — so a later failure can't lose it. Kept separate from
     * `consignment_id` (the finally recorded value) so a late response can
     * never overwrite an admin's reconciliation.
     */
    public function up(): void
    {
        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->string('returned_consignment_id')->nullable()->after('tracking_code');
            $table->string('returned_tracking_code')->nullable()->after('returned_consignment_id');
            $table->timestamp('returned_at')->nullable()->after('returned_tracking_code');
        });
    }

    /**
     * Refuses rather than silently dropping courier-returned IDs: they are
     * the only record of parcels the courier confirmed creating.
     */
    public function down(): void
    {
        if (DB::table('courier_submissions')->whereNotNull('returned_consignment_id')->exists()) {
            throw new RuntimeException('Refusing to roll back: courier_submissions holds courier-returned consignment IDs, which are audit records. Export them first and remove this guard deliberately if the rollback is really intended.');
        }

        Schema::table('courier_submissions', function (Blueprint $table) {
            $table->dropColumn(['returned_consignment_id', 'returned_tracking_code', 'returned_at']);
        });
    }
};
