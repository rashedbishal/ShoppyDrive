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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_password')->default(true)->after('password');
        });

        // Existing users created via social login never set a real password —
        // flag them so the login/registration flows can guide them correctly.
        DB::table('users')
            ->whereIn('id', DB::table('social_accounts')->select('user_id'))
            ->update(['has_password' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_password');
        });
    }
};
