<?php

namespace App\Concerns;

use App\Support\LayoutCache;

/**
 * Clears this model's LayoutCache entries whenever a row is saved, deleted
 * or restored. The using model declares which entries depend on it via a
 * `LAYOUT_CACHE_KEYS` constant.
 */
trait FlushesLayoutCache
{
    public static function bootFlushesLayoutCache(): void
    {
        $flush = fn () => LayoutCache::forget(...static::LAYOUT_CACHE_KEYS);

        static::saved($flush);
        static::deleted($flush);

        if (method_exists(static::class, 'restored')) {
            static::restored($flush);
        }
    }
}
