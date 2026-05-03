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
        Schema::create('coupon_uses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // order_id will be added after orders table is created
            $table->unsignedBigInteger('order_id'); // Temporarily unsignedBigInteger
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index('coupon_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');
    }
};
