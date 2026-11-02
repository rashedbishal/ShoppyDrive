<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * MED-06: read-only preflight for the unique indexes on
 * `product_variants.sku` and `attribute_values(attribute_id, value)`.
 * Uses the exact same grouping and NULL handling as the index migration's
 * own duplicate check (`database/migrations/2026_10_31_223219_...php`), so
 * this can be run independently — before the migration, or any time after,
 * on a branch where it already ran — with an answer that always agrees
 * with what the migration itself would find. Never modifies data.
 */
class VariantAttributeDuplicateCheck
{
    /**
     * @return array{sku: list<array{value: string, rows: int, ids: list<int>}>, attribute_value: list<array{attribute_id: int, value: string, rows: int, ids: list<int>}>}
     */
    public static function find(): array
    {
        return [
            'sku' => self::findSkuDuplicates(),
            'attribute_value' => self::findAttributeValueDuplicates(),
        ];
    }

    /**
     * @param  array{sku: list<array{value: string, rows: int, ids: list<int>}>, attribute_value: list<array{attribute_id: int, value: string, rows: int, ids: list<int>}>}  $duplicates
     */
    public static function hasDuplicates(array $duplicates): bool
    {
        return $duplicates['sku'] !== [] || $duplicates['attribute_value'] !== [];
    }

    /**
     * @param  array{sku: list<array{value: string, rows: int, ids: list<int>}>, attribute_value: list<array{attribute_id: int, value: string, rows: int, ids: list<int>}>}  $duplicates
     */
    public static function describe(array $duplicates): string
    {
        $lines = [];

        foreach ($duplicates['sku'] as $row) {
            $lines[] = "product_variants.sku = {$row['value']} on {$row['rows']} variants (ids ".implode(', ', $row['ids']).')';
        }

        foreach ($duplicates['attribute_value'] as $row) {
            $lines[] = "attribute_values.value = {$row['value']} on attribute_id {$row['attribute_id']}, {$row['rows']} rows (ids ".implode(', ', $row['ids']).')';
        }

        return implode("\n", $lines);
    }

    /**
     * Same filter and grouping as the migration's duplicateSkus(): only
     * non-NULL SKUs are compared, under the column's own collation.
     *
     * @return list<array{value: string, rows: int, ids: list<int>}>
     */
    private static function findSkuDuplicates(): array
    {
        $skus = DB::table('product_variants')
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('sku')
            ->pluck('sku');

        return $skus->map(function ($sku): array {
            $ids = DB::table('product_variants')->where('sku', $sku)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

            return ['value' => (string) $sku, 'rows' => count($ids), 'ids' => $ids];
        })->all();
    }

    /**
     * Same filter and grouping as the migration's duplicateAttributeValues():
     * grouped by (attribute_id, value), under the column's own collation.
     *
     * @return list<array{attribute_id: int, value: string, rows: int, ids: list<int>}>
     */
    private static function findAttributeValueDuplicates(): array
    {
        $groups = DB::table('attribute_values')
            ->groupBy('attribute_id', 'value')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('attribute_id')
            ->orderBy('value')
            ->get(['attribute_id', 'value']);

        return $groups->map(function ($group): array {
            $ids = DB::table('attribute_values')
                ->where('attribute_id', $group->attribute_id)
                ->where('value', $group->value)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return [
                'attribute_id' => (int) $group->attribute_id,
                'value' => (string) $group->value,
                'rows' => count($ids),
                'ids' => $ids,
            ];
        })->all();
    }
}
