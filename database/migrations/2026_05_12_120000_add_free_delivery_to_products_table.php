<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('free_delivery')->default(false)->after('is_digital');
            $table->unsignedInteger('free_delivery_min_qty')->nullable()->after('free_delivery')
                ->comment('Null = any qty qualifies. Set to N = need qty >= N for free delivery');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['free_delivery', 'free_delivery_min_qty']);
        });
    }
};
