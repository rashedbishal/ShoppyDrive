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
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['secondary_cta_label', 'secondary_cta_href']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('secondary_cta_label')->nullable();
            $table->string('secondary_cta_href')->nullable();
        });
    }
};
