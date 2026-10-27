<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * order_items already denormalizes product_name/sku/product_snapshot
     * specifically so a line item survives its product being deleted — the
     * original cascadeOnDelete() contradicted that by deleting the order
     * item (and silently shrinking its order) whenever the product was
     * removed. Admin\ProductController::destroy() now refuses to delete a
     * product with any order items in the first place, but this fixes the
     * underlying FK so any other delete path can't destroy order history.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
