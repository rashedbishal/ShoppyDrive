<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-001 (L3): an append-only record of every attempt to permanently
     * delete an order — successful, refused or failed — including a snapshot
     * of the order. Deliberately has no foreign keys: it must outlive the
     * order, the customer and the admin it describes, and it is a separate
     * table so clearing the activity log (audit trail) never touches it.
     * Passwords are never stored.
     */
    public function up(): void
    {
        Schema::create('order_deletion_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_status', 20)->nullable();
            $table->string('payment_status', 20)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->unsignedInteger('item_count')->nullable();
            $table->json('inventory_movements')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('order_deleted_at')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('step', 20);
            $table->string('outcome', 40);
            $table->json('refusal_reasons')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_deletion_audits') && DB::table('order_deletion_audits')->exists()) {
            throw new RuntimeException('Refusing to drop order_deletion_audits: it holds permanent order-deletion audit records.');
        }

        Schema::dropIfExists('order_deletion_audits');
    }
};
