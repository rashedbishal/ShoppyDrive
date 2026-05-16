<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the orders.payment_method enum to include 'zinipay' (MySQL only)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','zinipay') NOT NULL DEFAULT 'cod'");
        }

        // Add gateway columns to payments (re-add what SSLCommerz migration removed, now for ZiniPay)
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('transaction_id')->nullable()->after('amount');
            $table->string('val_id')->nullable()->after('transaction_id')->comment('ZiniPay val_id / our order_number reference');
            $table->json('gateway_response')->nullable()->after('val_id');

            $table->index('val_id');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod') NOT NULL DEFAULT 'cod'");
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['val_id']);
            $table->dropColumn(['transaction_id', 'val_id', 'gateway_response']);
        });
    }
};
