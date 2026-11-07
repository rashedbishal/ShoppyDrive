<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront search (SearchController) and catalog browsing
     * (ProductListingController) both match `name`/`short_description`
     * with a leading-wildcard LIKE, which can never use a plain B-tree
     * index. Verified via EXPLAIN + timing against a 175k-row synthetic
     * copy of this table: the "few or no matches" case (the common case
     * for autocomplete firing on every keystroke) took ~160-190ms doing a
     * full table scan; the same query via this FULLTEXT index in boolean
     * mode took ~4-5ms. See App\Support\ProductSearchQuery for the query
     * side and its documented semantics trade-off (word/word-prefix
     * matching instead of arbitrary substring matching, for terms of 3+
     * characters on MySQL only — `sku` and short/edge-case terms are
     * deliberately left on the original LIKE query, unchanged).
     */
    public function up(): void
    {
        // FULLTEXT isn't portable — SQLite (this app's test connection, per
        // phpunit.xml) throws rather than ignoring an unsupported index type.
        // Production runs MariaDB, whose PDO driver name still reports
        // 'mysql' (confirmed against production phpMyAdmin, DB-005), but
        // 'mariadb' is accepted too in case that ever changes.
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'short_description']);
        });
    }

    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText(['name', 'short_description']);
        });
    }
};
