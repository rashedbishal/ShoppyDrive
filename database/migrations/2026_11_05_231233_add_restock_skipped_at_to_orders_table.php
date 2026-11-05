<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('restock_skipped_at')->nullable()->after('lost_confirmed_at')
                ->comment('BIZ-012: set when an admin refunds a whole shipped/delivered order without confirming the goods came back. Makes InventoryService::restoreForOrder() skip restocking for this order. Null for every ordinary order.');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('restock_skipped_at');
        });
    }
};
