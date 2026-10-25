<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 10)->nullable()->unique()->after('id');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            $table->foreignId('referral_discount_used_order_id')->nullable()->after('referred_by_id')->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('points_balance')->default(0)->after('referral_discount_used_order_id');
        });

        // Backfill existing rows so referral_code is never null for a pre-existing user.
        DB::table('users')->whereNull('referral_code')->orderBy('id')->each(function ($user) {
            do {
                $code = Str::upper(Str::random(8));
            } while (DB::table('users')->where('referral_code', $code)->exists());

            DB::table('users')->where('id', $user->id)->update(['referral_code' => $code]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_discount_used_order_id');
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropColumn(['referral_code', 'points_balance']);
        });
    }
};
