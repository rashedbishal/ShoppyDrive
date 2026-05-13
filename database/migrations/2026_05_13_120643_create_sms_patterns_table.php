<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_patterns', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['bkash', 'nagad']);
            $table->string('name');
            $table->string('identifier')->comment('Substring that must exist in SMS to match this pattern');
            $table->string('txn_id_regex')->comment('Regex with one capture group for the transaction ID');
            $table->string('amount_regex')->comment('Regex with one capture group for the amount');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_patterns');
    }
};
