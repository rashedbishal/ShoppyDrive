<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard device split: each day's visitors by device type, counted at the
 * same moment visitor_count is. Days before this migration stay at zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_visits', function (Blueprint $table) {
            $table->unsignedInteger('mobile_visitors')->default(0)->after('page_view_count');
            $table->unsignedInteger('tablet_visitors')->default(0)->after('mobile_visitors');
            $table->unsignedInteger('desktop_visitors')->default(0)->after('tablet_visitors');
        });
    }

    public function down(): void
    {
        Schema::table('daily_visits', function (Blueprint $table) {
            $table->dropColumn(['mobile_visitors', 'tablet_visitors', 'desktop_visitors']);
        });
    }
};
