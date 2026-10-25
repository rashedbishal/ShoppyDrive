<?php

declare(strict_types=1);

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
        Schema::create('referral_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type');
            $table->integer('points');
            $table->decimal('currency_amount', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_point_transactions');
    }
};
