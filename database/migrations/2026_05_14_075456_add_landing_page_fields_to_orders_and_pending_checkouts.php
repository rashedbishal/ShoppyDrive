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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete()->after('user_id');
            $table->string('guest_token')->nullable()->unique()->after('landing_page_id');
        });

        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->foreignId('landing_page_id')->nullable()->constrained('landing_pages')->nullOnDelete()->after('cart_id');
            $table->string('guest_token')->nullable()->unique()->after('landing_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['landing_page_id']);
            $table->dropColumn(['landing_page_id', 'guest_token']);
        });

        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->dropForeign(['landing_page_id']);
            $table->dropColumn(['landing_page_id', 'guest_token']);
        });
    }
};
