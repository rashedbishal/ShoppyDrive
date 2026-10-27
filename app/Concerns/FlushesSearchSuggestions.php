<?php

namespace App\Concerns;

use App\Support\SearchSuggestionCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Clears the search-suggestion cache when a row is created, deleted or
 * restored, or when one of the columns suggestions actually use changes.
 * The using model lists those columns in a `SEARCH_SUGGESTION_FIELDS`
 * constant (null = any change counts), so unrelated writes — e.g. stock
 * changing on every order — leave the cache alone.
 */
trait FlushesSearchSuggestions
{
    public static function bootFlushesSearchSuggestions(): void
    {
        static::created(fn () => SearchSuggestionCache::flush());

        // `updated` (not `saved`) also fires for increment()/decrement() —
        // e.g. stock taken by an order — with only that column changed, so
        // those correctly leave the cache alone.
        static::updated(function (Model $model): void {
            $fields = static::SEARCH_SUGGESTION_FIELDS;

            if ($fields === null || $model->wasChanged($fields)) {
                SearchSuggestionCache::flush();
            }
        });

        static::deleted(fn () => SearchSuggestionCache::flush());

        if (method_exists(static::class, 'restored')) {
            static::restored(fn () => SearchSuggestionCache::flush());
        }
    }
}
