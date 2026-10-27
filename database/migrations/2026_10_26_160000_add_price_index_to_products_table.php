<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storefront catalog/search browsing (ProductListingController,
     * SearchController) both sort by `price` (price_asc/price_desc) and
     * filter by a min/max price range, always on top of the low-selectivity
     * `active()`/`published()` scopes. Without this index MySQL picks the
     * is_active index and filesorts the entire active+published set before
     * applying LIMIT — verified via EXPLAIN against an 8k-row synthetic
     * copy of this table: "Using filesort" scanning ~7.2k candidate rows,
     * versus an index-ordered scan stopping after ~13 rows once this index
     * exists. The range-filter query drops from a full is_active scan to a
     * `range` scan on this index with no filesort either. (It does *not*
     * help the separate `MAX(price)` aggregate used for the filter slider's
     * ceiling — that still scans via is_active either way, since the WHERE
     * clause isn't covered by a price-only index.)
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price']);
        });
    }
};
