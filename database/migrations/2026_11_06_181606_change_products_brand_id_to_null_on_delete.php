<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-001: deleting a brand cascaded to any trashed products still using
     * it (the delete check only sees active products), permanently taking
     * their reviews, stock history and variants too. A deleted brand now
     * just clears brand_id, so it can never destroy a product. Active
     * products still block the delete in Admin\BrandController.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
        });
    }
};
