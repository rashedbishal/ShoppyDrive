<?php

namespace App\Concerns;

use App\Support\CatalogFilterCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Clears the product-listing filter options when a row is created, deleted
 * or restored, or when one of the columns they depend on changes. The using
 * model lists those columns in a `CATALOG_FILTER_FIELDS` constant (null =
 * any change counts), so unrelated writes — e.g. stock changing on every
 * order — leave the cache alone.
 */
trait FlushesCatalogFilters
{
    public static function bootFlushesCatalogFilters(): void
    {
        static::created(fn () => CatalogFilterCache::flush());

        // `updated` also fires for increment()/decrement(), with only that
        // column changed.
        static::updated(function (Model $model): void {
            $fields = static::CATALOG_FILTER_FIELDS;

            if ($fields === null || $model->wasChanged($fields)) {
                CatalogFilterCache::flush();
            }
        });

        static::deleted(fn () => CatalogFilterCache::flush());

        if (method_exists(static::class, 'restored')) {
            static::restored(fn () => CatalogFilterCache::flush());
        }
    }
}
