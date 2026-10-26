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
        Schema::table('users', function (Blueprint $table) {
            // Consecutive days claimed so far — not capped at the display/reward
            // cap, so a streak kept well past the cap still reads as "day 46" etc.
            // even though the reward formula itself stops growing at the cap.
            $table->unsignedInteger('login_streak_count')->default(0)->after('points_balance');
            // Asia/Dhaka calendar date of the last claimed day. Comparing this
            // against "today"/"yesterday" in that same timezone is what drives
            // increment vs. reset vs. already-claimed-today.
            $table->date('login_streak_date')->nullable()->after('login_streak_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login_streak_count', 'login_streak_date']);
        });
    }
};
