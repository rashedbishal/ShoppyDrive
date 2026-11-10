<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OPS-006: when this customer was last actually sent an abandoned-cart
     * reminder, for the per-customer cooldown. Nullable and additive.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_abandoned_cart_reminder_at')->nullable()->after('abandoned_cart_reminders_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_abandoned_cart_reminder_at');
        });
    }
};
