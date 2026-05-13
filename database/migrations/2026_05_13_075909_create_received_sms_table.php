<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_sms', function (Blueprint $table) {
            $table->id();
            $table->string('sender');
            $table->text('content');
            $table->timestamp('received_at');
            $table->string('parsed_transaction_id')->nullable()->index();
            $table->decimal('parsed_amount', 10, 2)->nullable();
            $table->boolean('is_matched')->default(false)->index();
            $table->foreignId('matched_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_sms');
    }
};
