<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pathao_consignment_id')->nullable()->after('tracking_number');
            $table->string('pathao_order_status')->nullable()->after('pathao_consignment_id');
            $table->decimal('pathao_delivery_fee', 10, 2)->nullable()->after('pathao_order_status');
            $table->timestamp('pathao_sent_at')->nullable()->after('pathao_delivery_fee');

            $table->index('pathao_consignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['pathao_consignment_id']);
            $table->dropColumn(['pathao_consignment_id', 'pathao_order_status', 'pathao_delivery_fee', 'pathao_sent_at']);
        });
    }
};
