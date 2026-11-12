<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * A variant's `attributes` JSON ({"Color": "Red", "Set": "12pcs"}) is what the
 * storefront, cart, landing page and order snapshots show and match on, keyed
 * by attribute name. It is built from `attribute_value_ids`, so renaming an
 * attribute or a value rebuilds it here for every variant using it.
 */
final class VariantAttributeLabels
{
    /**
     * {"Color": "Red", "Set": "12pcs"} for these value ids, in the given order.
     * Unknown ids are skipped.
     *
     * @param  array<int, int>  $valueIds
     * @param  Collection<int, AttributeValue>|null  $values  Already-loaded values (with `attribute`), keyed by id.
     * @return array<string, string>
     */
    public static function for(array $valueIds, ?Collection $values = null): array
    {
        $values ??= AttributeValue::with('attribute')->whereKey($valueIds)->get()->keyBy('id');
        $labels = [];

        foreach ($valueIds as $valueId) {
            $value = $values[$valueId] ?? null;

            if ($value?->attribute !== null) {
                $labels[$value->attribute->name] = $value->value;
            }
        }

        return $labels;
    }

    /**
     * Rebuilds the labels of every variant that uses any of these values,
     * including retired variants kept for carts and order history.
     *
     * @param  array<int, int>  $valueIds
     */
    public static function refreshForValues(array $valueIds): void
    {
        $valueIds = array_map('intval', $valueIds);

        if ($valueIds === []) {
            return;
        }

        ProductVariant::query()
            ->whereNotNull('attribute_value_ids')
            ->select(['id', 'attribute_value_ids', 'attributes'])
            ->chunkById(500, function (Collection $variants) use ($valueIds): void {
                $affected = $variants->filter(fn (ProductVariant $variant): bool => array_intersect(array_map('intval', $variant->attribute_value_ids ?? []), $valueIds) !== []);

                if ($affected->isEmpty()) {
                    return;
                }

                $values = AttributeValue::with('attribute')
                    ->whereKey($affected->flatMap(fn (ProductVariant $variant) => $variant->attribute_value_ids)->unique()->all())
                    ->get()
                    ->keyBy('id');

                foreach ($affected as $variant) {
                    ProductVariant::query()->whereKey($variant->id)->update([
                        'attributes' => json_encode(self::for(array_map('intval', $variant->attribute_value_ids), $values)),
                    ]);
                }
            });
    }
}
