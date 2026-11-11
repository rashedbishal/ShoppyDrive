<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Most units one customer can order through a landing page in a single
     * order (all variants together). Additive; existing pages keep the 100
     * they were always capped at.
     */
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_order_quantity')->default(100)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn('max_order_quantity');
        });
    }
};
