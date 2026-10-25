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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('used_referral_discount')->default(false)->after('coupon_code');
            $table->unsignedInteger('points_redeemed')->nullable()->after('used_referral_discount');
            $table->timestamp('points_refunded_at')->nullable()->after('points_redeemed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['used_referral_discount', 'points_redeemed', 'points_refunded_at']);
        });
    }
};
