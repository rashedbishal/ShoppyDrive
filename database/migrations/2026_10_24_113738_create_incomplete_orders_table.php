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
        Schema::create('incomplete_orders', function (Blueprint $table) {
            $table->id();
            // One row per checkout attempt (a browser tab's checkout session),
            // identified client-side — repeated edits within the same attempt
            // update this row instead of creating duplicates; a new page visit
            // generates a new token, so a phone number can legitimately appear
            // more than once across separate abandoned attempts.
            $table->string('attempt_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->json('items');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();

            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomplete_orders');
    }
};
