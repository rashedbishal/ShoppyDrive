<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order placed with "Buy Now" is built from that one product alone and
     * never touches the customer's cart. The order confirmation page needs to
     * know, so it does not empty the cart the customer still has.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_buy_now')->default(false)->after('order_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_buy_now');
        });
    }
};
