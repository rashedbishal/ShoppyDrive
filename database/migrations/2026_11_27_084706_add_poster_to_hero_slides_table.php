<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMG-F3: an optional still image for a video hero slide, shown (and
     * preloaded) while the video loads. Nullable and additive; existing
     * slides are unchanged and keep working without one.
     */
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('poster')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('poster');
        });
    }
};
