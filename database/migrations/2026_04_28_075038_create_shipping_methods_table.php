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
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['flat', 'weight_based', 'free']);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('min_order_for_free', 10, 2)->nullable();
            $table->string('estimated_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
