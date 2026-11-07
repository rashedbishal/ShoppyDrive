<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','zinipay','mobile_banking') NOT NULL DEFAULT 'cod'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','zinipay') NOT NULL DEFAULT 'cod'");
        }
    }
};
