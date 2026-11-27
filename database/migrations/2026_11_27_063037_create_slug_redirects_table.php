<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DISC-91: a renamed product, category or sub-category's previous slugs,
     * so its old URL can 301 to the current one. New table only; no existing
     * table or row is touched, and nothing is backfilled (earlier slugs were
     * never stored).
     */
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            // The URL the old slug lived under: 'product' (/product/{slug}) or
            // 'category' (/category/{slug}, shared by categories and
            // sub-categories).
            $table->string('namespace', 20);
            $table->string('old_slug');
            // The record it now belongs to; the redirect always uses that
            // record's current slug, so renames never chain.
            $table->morphs('redirectable');
            $table->timestamps();

            $table->unique(['namespace', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
