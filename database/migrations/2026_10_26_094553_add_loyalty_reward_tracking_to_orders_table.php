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
            // Set once the delayed scheduled grant has actually credited the
            // referrer's points for this order — distinct from
            // `used_referral_discount` (set immediately, marks the referred
            // customer's one-time discount as spent) so the grant command
            // can tell "not due yet" apart from "already granted".
            $table->timestamp('referral_reward_granted_at')->nullable()->after('points_refunded_at');
            // Set once the delayed scheduled grant has credited this order's
            // spend-based loyalty points to its purchaser.
            $table->timestamp('loyalty_points_granted_at')->nullable()->after('referral_reward_granted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['referral_reward_granted_at', 'loyalty_points_granted_at']);
        });
    }
};
