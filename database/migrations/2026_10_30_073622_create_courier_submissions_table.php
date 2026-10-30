<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per attempt to book an order with a courier (COURIER-SEND-RACE-001).
     * `active_order_id` equals `order_id` only while the attempt is unresolved
     * (submitting/unknown) and is NULL otherwise; its unique index is what
     * guarantees at most one unresolved attempt per order across both couriers.
     */
    public function up(): void
    {
        Schema::create('courier_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier', 20);
            $table->string('state', 20);
            $table->unsignedBigInteger('active_order_id')->nullable()->unique();
            $table->string('source', 20)->default('app');
            $table->string('reference');
            $table->string('consignment_id')->nullable()->index();
            $table->string('tracking_code')->nullable();
            $table->string('failure_kind', 40)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('curl_errno')->nullable();
            $table->unsignedInteger('request_bytes_sent')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('claimed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('resolution', 40)->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'courier']);
        });
    }

    /**
     * COURIER-SEND-RACE-002 (M3): refuses to drop real send attempts and
     * reconciliation records. Rows created by the legacy backfill are removed
     * by that migration's own rollback (it runs first), and the consignment
     * IDs they mirror stay on `orders` regardless — so after a full rollback
     * only genuine operational history can block this, and it does.
     */
    public function down(): void
    {
        if (Schema::hasTable('courier_submissions')
            && DB::table('courier_submissions')->where('source', '!=', 'legacy_backfill')->exists()) {
            throw new RuntimeException('Refusing to drop courier_submissions: it contains real courier send attempts and reconciliation records (source != legacy_backfill). Export them first and remove this guard deliberately if the rollback is really intended.');
        }

        Schema::dropIfExists('courier_submissions');
    }
};
