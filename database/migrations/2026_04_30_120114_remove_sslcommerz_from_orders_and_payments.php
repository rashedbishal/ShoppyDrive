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

        // PostgreSQL: replace check constraint to allow only 'cod'
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('cod'))");
        DB::statement("ALTER TABLE orders ALTER COLUMN payment_method SET DEFAULT 'cod'");

        // Drop SSLCommerz-specific columns from payments
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
        });

        DB::statement('DROP INDEX IF EXISTS payments_gateway_response_idx');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'val_id', 'gateway_response']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('sslcommerz', 'cod'))");
        DB::statement('ALTER TABLE orders ALTER COLUMN payment_method DROP DEFAULT');

        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable();
            $table->string('val_id')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->index('transaction_id');
        });
    }
};
