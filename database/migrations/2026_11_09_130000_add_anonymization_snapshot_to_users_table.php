<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin-deleted customer is anonymized, never hard-deleted (DB-001),
     * which irreversibly overwrites name/phone/avatar/password/remember_token
     * and the address book, and deletes social-account rows outright — with
     * no way back if the wrong customer was anonymized by mistake. This
     * column holds everything User::anonymize() is about to destroy, so
     * User::restoreFromAnonymization() can undo it later. Owner decision:
     * kept indefinitely, not purged after a retention window.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('anonymization_snapshot')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymization_snapshot');
        });
    }
};
