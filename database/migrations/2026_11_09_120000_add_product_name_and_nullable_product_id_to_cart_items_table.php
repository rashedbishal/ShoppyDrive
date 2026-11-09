<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BE-002 retest: `product_id` had `cascadeOnDelete()`, so permanently
     * deleting a product (the real admin path — Admin\ProductController::
     * destroy() always force-deletes, never soft-deletes) silently
     * destroyed the cart_item row itself, for every cart it was in — a
     * logged-in customer's item just vanished with no message at all,
     * worse than a guest's generic "no longer available" at checkout.
     * `product_name` is a display-only snapshot (same convention as
     * `order_items.product_name`), populated by CartManager whenever a
     * row is created/updated, so the row can still be shown and named
     * after the product it pointed to is gone for good. `variant_id` also
     * needs the same change: `product_variants.product_id` itself
     * cascades, so deleting a product with variants also deletes its
     * ProductVariant rows, which would otherwise still cascade this row
     * away via its own variant_id FK regardless of the product_id fix
     * above — variant_id was already nullable, so only its FK action
     * needs to change.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['variant_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['variant_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->dropColumn('product_name');
        });
    }
};
