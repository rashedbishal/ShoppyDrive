<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_seen')->default(false)->after('status');
            $table->timestamp('seen_at')->nullable()->after('is_seen');
        });

        // Every order placed before this column existed has already been
        // handled by someone — only orders created from here on should
        // count as "new" on the admin sidebar badge.
        DB::table('orders')->update(['is_seen' => true, 'seen_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_seen', 'seen_at']);
        });
    }
};
