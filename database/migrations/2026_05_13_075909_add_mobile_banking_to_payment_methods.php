<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','zinipay','mobile_banking') NOT NULL DEFAULT 'cod'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','zinipay') NOT NULL DEFAULT 'cod'");
        }
    }
};
