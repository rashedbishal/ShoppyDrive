<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The product-listing filter sidebar's options (categories and brands with
 * product counts, colours/sizes, the highest price). They're identical for
 * every visitor and every listing page, so each is one cache entry.
 *
 * - Cleared on any change they depend on (see the FlushesCatalogFilters
 *   trait, plus explicit flush() calls after bulk writes that fire no
 *   model events).
 * - Expires exactly when the next scheduled product goes live, since
 *   counts and the highest price depend on `published_at <= now()` and no
 *   event announces that moment; otherwise after an hour as a backstop.
 * - Stored JSON-round-tripped: exactly what the page receives, and
 *   object-free (cache.serializable_classes is false).
 */
final class CatalogFilterCache
{
    public const MAX_TTL_SECONDS = 3600;

    private const KEYS = ['categories', 'brands', 'attributes', 'max_price'];

    private const PREFIX = 'catalog_filters.';

    /**
     * @return list<array<string, mixed>>
     */
    public static function categories(): array
    {
        return self::remember('categories', fn () => Category::query()
            ->select(['id', 'name', 'slug', 'image'])
            ->active()
            ->withCount([
                'products as products_count' => static fn ($productQuery) => $productQuery->visible(),
            ])
            ->with([
                'subCategories' => static function ($childQuery): void {
                    $childQuery
                        ->select(['id', 'category_id', 'name', 'slug', 'image'])
                        ->active()
                        ->orderBy('sort_order')
                        ->withCount([
                            'products as products_count' => static fn ($productQuery) => $productQuery->visible(),
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->get());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function brands(): array
    {
        return self::remember('brands', fn () => Brand::query()
            ->select(['id', 'name', 'slug'])
            ->active()
            ->withCount([
                'products as products_count' => static fn ($productQuery) => $productQuery->visible(),
            ])
            ->orderBy('name')
            ->get());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function attributes(): array
    {
        return self::remember('attributes', fn () => Attribute::query()
            ->with([
                'values' => static fn ($valueQuery) => $valueQuery->orderBy('sort_order'),
            ])
            ->orderBy('name')
            ->get());
    }

    public static function maxPrice(): int
    {
        return self::remember('max_price', fn () => (int) (Product::query()
            ->visible()
            ->max('price') ?? 0));
    }

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget(self::PREFIX.$key);
        }
    }

    private static function remember(string $key, Closure $build): mixed
    {
        $cached = Cache::get(self::PREFIX.$key);

        if ($cached !== null) {
            return $cached;
        }

        $value = json_decode(json_encode($build(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        Cache::put(self::PREFIX.$key, $value, self::secondsUntilNextScheduledProduct());

        return $value;
    }

    private static function secondsUntilNextScheduledProduct(): int
    {
        $next = Product::query()
            ->active()
            ->where('published_at', '>', now())
            ->min('published_at');

        if ($next === null) {
            return self::MAX_TTL_SECONDS;
        }

        return max(1, min(self::MAX_TTL_SECONDS, (int) ceil(now()->diffInSeconds($next, absolute: true))));
    }
}
