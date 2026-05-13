<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_sms', function (Blueprint $table) {
            $table->string('provider')->default('unknown')->after('sender')
                ->comment('bkash | nagad | unknown');
        });
    }

    public function down(): void
    {
        Schema::table('received_sms', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
