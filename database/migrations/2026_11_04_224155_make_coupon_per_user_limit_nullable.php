<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Null means no per-customer limit. The default stays 1, so a coupon
     * created without mentioning the limit behaves as it always has.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->integer('per_user_limit')->nullable()->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Unlimited coupons become limited to one use, since the old column can't
     * hold "no limit".
     */
    public function down(): void
    {
        DB::table('coupons')->whereNull('per_user_limit')->update(['per_user_limit' => 1]);

        Schema::table('coupons', function (Blueprint $table) {
            $table->integer('per_user_limit')->nullable(false)->default(1)->change();
        });
    }
};
