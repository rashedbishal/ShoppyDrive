<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('lost_confirmed_at')->nullable()->after('stock_restored_at')
                ->comment('FSA-11: set only by the mother-admin "confirm lost" action, after existing courier evidence already shows not-delivered. Makes InventoryService::restoreForOrder() skip restocking for this order — the goods are gone, not returned. Null for every ordinary order.');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('lost_confirmed_at');
        });
    }
};
