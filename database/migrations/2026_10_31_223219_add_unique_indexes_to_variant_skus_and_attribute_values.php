<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MED-06. Uniqueness follows the columns' utf8mb4_unicode_ci collation,
     * the same equality ProductRequest's and AttributeValueRequest's checks
     * see on MySQL, so `ABC`/`abc` collide. NULL SKUs never collide, which is
     * how a variant has no SKU.
     *
     * Deploy safety: `deploy.yml`'s post-deploy script runs under `set -e`
     * and chains `migrate --force` before `cache:clear`/`config:cache`/etc.
     * A migration that throws here would abort that script mid-way, leaving
     * production with every cache cleared and none of them rebuilt. So
     * unlike the first version of this migration, an index whose table has
     * duplicates is never fatal: it's independently skipped — not added,
     * with a clear warning logged via `Log::warning()` naming the exact
     * duplicate group IDs — so `migrate --force` always exits 0 and the rest
     * of the deploy script still runs regardless of what this finds. No data
     * is ever changed here, and choosing which duplicate row survives stays
     * a human decision, made separately before re-running this migration —
     * it's safe to run again: a skipped index is retried next time, and an
     * already-added one is left alone.
     */
    public function up(): void
    {
        $this->addUniqueIndexUnlessDuplicates(
            'product_variants',
            'product_variants_sku_unique',
            $this->duplicateSkus(),
            fn (Blueprint $table) => $table->unique('sku', 'product_variants_sku_unique'),
        );

        $this->addUniqueIndexUnlessDuplicates(
            'attribute_values',
            'attribute_values_attribute_id_value_unique',
            $this->duplicateAttributeValues(),
            fn (Blueprint $table) => $table->unique(['attribute_id', 'value'], 'attribute_values_attribute_id_value_unique'),
        );
    }

    public function down(): void
    {
        if (Schema::hasIndex('attribute_values', 'attribute_values_attribute_id_value_unique')) {
            Schema::table('attribute_values', function (Blueprint $table): void {
                $table->dropUnique('attribute_values_attribute_id_value_unique');
            });
        }

        if (Schema::hasIndex('product_variants', 'product_variants_sku_unique')) {
            Schema::table('product_variants', function (Blueprint $table): void {
                $table->dropUnique('product_variants_sku_unique');
            });
        }
    }

    /** @param  Collection<int, object{rows_found: int, first_id: int}>  $duplicates */
    private function addUniqueIndexUnlessDuplicates(string $table, string $indexName, Collection $duplicates, callable $addIndex): void
    {
        if (Schema::hasIndex($table, $indexName)) {
            return;
        }

        if ($duplicates->isNotEmpty()) {
            Log::warning(sprintf(
                'MED-06: skipped adding %s because duplicates exist. Nothing was changed. Resolve by hand, then re-run this migration. Groups starting at IDs [%s].',
                $indexName,
                $duplicates->pluck('first_id')->implode(', '),
            ));

            return;
        }

        Schema::table($table, $addIndex);
    }

    /** @return Collection<int, object{rows_found: int, first_id: int}> */
    private function duplicateSkus(): Collection
    {
        return DB::table('product_variants')
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('COUNT(*) as rows_found, MIN(id) as first_id')
            ->get();
    }

    /** @return Collection<int, object{rows_found: int, first_id: int}> */
    private function duplicateAttributeValues(): Collection
    {
        return DB::table('attribute_values')
            ->groupBy('attribute_id', 'value')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('COUNT(*) as rows_found, MIN(id) as first_id')
            ->get();
    }
};
