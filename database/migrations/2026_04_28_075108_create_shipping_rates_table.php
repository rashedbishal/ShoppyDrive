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
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->decimal('min_weight', 8, 2);
            $table->decimal('max_weight', 8, 2);
            $table->decimal('rate', 10, 2);
            $table->timestamps();

            $table->index('method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
