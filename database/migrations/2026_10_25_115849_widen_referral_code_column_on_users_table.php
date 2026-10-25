<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::table(...)->change() needs doctrine/dbal, which isn't
        // installed — a raw MODIFY avoids adding it just for a column
        // width change. Existing 8-char codes are untouched and still fit.
        // SQLite (used for tests) has no MODIFY COLUMN and no real length
        // enforcement anyway (VARCHAR is just type affinity there), so
        // there's nothing to actually alter on that driver.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users MODIFY referral_code VARCHAR(12) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users MODIFY referral_code VARCHAR(10) NULL');
        }
    }
};
