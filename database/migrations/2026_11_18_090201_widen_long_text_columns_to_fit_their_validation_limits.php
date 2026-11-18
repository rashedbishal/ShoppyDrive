<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DISC-69: TEXT holds 65,535 bytes, and utf8mb4 needs up to 4 bytes per
     * character (Bangla 3), so these columns could refuse text their form
     * accepts, a server error instead of a save (the DISC-68 kind of bug):
     * Policy & Info Pages content (form limit 100,000), product description
     * (20,000) and the courier readiness process list (20,000). MEDIUMTEXT
     * holds 16 MB. Type change only: every value, nullability and the
     * validation limits stay as they are.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->mediumText('content')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->mediumText('description')->nullable()->change();
        });

        Schema::table('courier_verification_readiness', function (Blueprint $table) {
            $table->mediumText('process_list')->nullable()->change();
        });
    }

    /**
     * Back to TEXT. Fails on MySQL (rather than cutting text) if a value
     * saved since is longer than TEXT can hold.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->text('content')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('courier_verification_readiness', function (Blueprint $table) {
            $table->text('process_list')->nullable()->change();
        });
    }
};
