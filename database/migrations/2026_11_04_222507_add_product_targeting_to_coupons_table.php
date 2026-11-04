<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every existing coupon stays 'all', so nothing changes for it. An order
     * item's coupon_applied is only set for a selected-items coupon; null
     * keeps the existing refund behaviour for every other order.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('applies_to')->default('all')->after('value');
        });

        Schema::create('coupon_product_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null means the whole product, every variant. Duplicates are
            // refused by CouponRequest: a unique index can't see NULLs as equal.
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'product_id', 'variant_id']);
        });

        Schema::create('coupon_category', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_sub_category', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_category_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'sub_category_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('coupon_applied')->nullable()->after('bundle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('coupon_applied');
        });

        Schema::dropIfExists('coupon_sub_category');
        Schema::dropIfExists('coupon_category');
        Schema::dropIfExists('coupon_product_targets');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
