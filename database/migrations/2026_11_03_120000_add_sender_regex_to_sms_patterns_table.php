<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_patterns', function (Blueprint $table) {
            $table->string('sender_regex')->nullable()->after('identifier')
                ->comment('Optional regex the SMS sender must match (1 capture group, unused). Null means unrestricted (FSA-02).');
        });
    }

    public function down(): void
    {
        Schema::table('sms_patterns', function (Blueprint $table) {
            $table->dropColumn('sender_regex');
        });
    }
};
