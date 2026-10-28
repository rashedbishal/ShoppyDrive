<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors change_order_items_product_id_to_null_on_delete: order_items
     * already denormalizes product_name/sku/product_snapshot specifically
     * so a line item survives its product/variant being deleted, but that
     * earlier fix only repointed the product_id FK — variant_id was left
     * on cascadeOnDelete(), so a hard-deleted ProductVariant (directly, or
     * via a hard-deleted Product cascading to its own variants) still
     * deleted the order_items row that referenced it, destroying order
     * history the product_id fix was meant to prevent. variant_id is
     * already nullable on this table, so nullOnDelete() needs no column
     * change.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }
};
