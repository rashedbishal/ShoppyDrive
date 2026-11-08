<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array COLUMNS = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    /**
     * SEC-014: the owner's decision is that this feature does not exist in
     * the app, so its unused columns go too. Older backups that still hold
     * them restore through DatabaseBackupFormat::RETIRED_COLUMNS.
     */
    public function up(): void
    {
        $existingColumns = array_values(array_filter(self::COLUMNS, fn (string $column): bool => Schema::hasColumn('users', $column)));

        if ($existingColumns === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn($existingColumns);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }
};
