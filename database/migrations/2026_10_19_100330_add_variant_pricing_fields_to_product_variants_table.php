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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('compare_price', 10, 2)->nullable()->after('price');
            $table->decimal('cost_price', 10, 2)->nullable()->after('compare_price');
            $table->integer('low_stock_threshold')->nullable()->default(5)->after('stock_quantity');
            $table->boolean('is_default')->default(false)->after('attributes');
            $table->foreignId('color_value_id')->nullable()->after('is_default')->constrained('attribute_values')->nullOnDelete();
            $table->foreignId('size_value_id')->nullable()->after('color_value_id')->constrained('attribute_values')->nullOnDelete();

            $table->unique(['product_id', 'color_value_id', 'size_value_id'], 'product_variants_product_color_size_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_product_color_size_unique');
            $table->dropConstrainedForeignId('size_value_id');
            $table->dropConstrainedForeignId('color_value_id');
            $table->dropColumn(['compare_price', 'cost_price', 'low_stock_threshold', 'is_default']);
        });
    }
};
