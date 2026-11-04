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
        Schema::table('products', function (Blueprint $table) {
            // Admin-controlled key/value pairs shown as the storefront's
            // "Product specifications" card — a JSON array of {key, value}
            // objects, so admins can name and order their own fields
            // instead of being limited to fixed columns.
            $table->json('specifications')->nullable()->after('short_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });
    }
};
