<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DISC-86: optional search-engine title and description for a brand page,
     * the same pair categories, products, blogs and pages already have.
     * Additive and nullable only; existing brands keep using their name and
     * description until an admin fills these in.
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
