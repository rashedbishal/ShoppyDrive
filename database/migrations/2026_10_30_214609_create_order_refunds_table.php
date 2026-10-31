<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PAY-RETURN-001: the single, durable ledger of money actually paid back
     * to a customer — return-based and whole-order refunds share it, so
     * neither can exceed what the other already refunded. Survives
     * activity-log clearing; never stores secrets.
     */
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('source', 20); // return | order
            $table->decimal('amount', 10, 2);
            $table->string('method', 100)->nullable();
            $table->string('previous_order_status', 20)->nullable();
            $table->string('resulting_order_status', 20)->nullable();
            $table->string('previous_payment_status', 20)->nullable();
            $table->string('resulting_payment_status', 20)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable(); // no FK: admins are hard-deleted
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_backfilled')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('order_refunds')->where('is_backfilled', false)->exists()) {
            throw new RuntimeException('Refusing to roll back: order_refunds holds refunds recorded by the application. Dropping it would erase the refund ledger.');
        }

        Schema::dropIfExists('order_refunds');
    }
};
