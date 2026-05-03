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
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->jsonb('countries'); // PostgreSQL specific JSONB type
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // GIN index for JSONB column
            $table->index('countries', null, 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
