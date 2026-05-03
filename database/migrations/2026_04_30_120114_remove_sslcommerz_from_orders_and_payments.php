<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate any sslcommerz orders to cod before tightening the constraint
        DB::table('orders')->where('payment_method', 'sslcommerz')->update(['payment_method' => 'cod']);

        // MySQL: modify the enum column to only allow 'cod'
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cod'])->default('cod')->change();
        });

        // Drop SSLCommerz-specific columns from payments (only if they exist)
        if (Schema::hasColumn('payments', 'transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['transaction_id']);
            });
        }

        if (Schema::hasColumn('payments', 'transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['transaction_id', 'val_id', 'gateway_response']);
            });
        }
    }

    public function down(): void
    {
        // Restore enum to allow both sslcommerz and cod
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['sslcommerz', 'cod'])->default('cod')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable();
            $table->string('val_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->index('transaction_id');
        });
    }
};
