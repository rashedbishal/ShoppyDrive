<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIZ-023: the page render a COD order came from, so a resubmission can never
 * become a second order, even after a deploy clears the cache. Existing orders
 * stay NULL, which a unique index allows any number of times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('order_token', 36)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['order_token']);
            $table->dropColumn('order_token');
        });
    }
};
