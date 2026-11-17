<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DISC-65: optional Bangla for a landing page. Additive only; every
     * existing page gets Bangla off and keeps working exactly as before.
     * `sections_bn` holds the Bangla text for the page builder's fields
     * (see LandingPage::banglaSections()); it is kept when Bangla is turned
     * off, so turning it back on brings the text back.
     */
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->boolean('bangla_enabled')->default(false)->after('max_order_quantity');
            $table->string('default_language', 2)->default('en')->after('bangla_enabled');
            $table->string('title_bn', 100)->nullable()->after('default_language');
            $table->json('sections_bn')->nullable()->after('sections');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['bangla_enabled', 'default_language', 'title_bn', 'sections_bn']);
        });
    }
};
