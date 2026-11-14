<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard "top searches" / "searches with no results". One row per Dhaka
 * day and search term with a running count, so the table stays small and
 * holds no visitor data (no IP, user or session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('term', 100);
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
