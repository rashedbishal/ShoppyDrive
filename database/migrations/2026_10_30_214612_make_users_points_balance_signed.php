<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PAY-RETURN-001: reversing order-earned points the customer already
     * spent may take the balance below zero (approved policy); future
     * earnings then offset it. INT UNSIGNED cannot hold that on MySQL.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points_balance')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('points_balance', '<', 0)->exists()) {
            throw new RuntimeException('Refusing to roll back: some customers have a negative points balance, which an unsigned column cannot hold.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->change();
        });
    }
};
