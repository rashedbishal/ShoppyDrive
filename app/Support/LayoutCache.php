<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\PopularKeyword;
use App\Models\PromotionalBanner;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed data that every page loads (shared Inertia props, delivery
 * rates). Cached forever and cleared the moment its source changes — see
 * the FlushesLayoutCache trait on each source model — so visitors never see
 * stale data. Writes that bypass model events (bulk `update()`/`delete()`)
 * must call forget() themselves.
 *
 * Everything is cached as plain arrays (each model's toArray(), i.e. exactly
 * what Inertia would send), never as model objects: `cache.serializable_classes`
 * is false, so objects read back from a persistent store come back as
 * __PHP_Incomplete_Class.
 */
final class LayoutCache
{
    public const SETTINGS = 'settings';

    public const NAV_CATEGORIES = 'nav_categories';

    public const HOMEPAGE_SETTINGS = 'homepage_settings';

    public const HERO_SLIDES = 'hero_slides';

    public const PROMOTIONAL_BANNERS = 'promotional_banners';

    public const POPULAR_KEYWORDS = 'popular_keywords';

    private const PREFIX = 'layout.';

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget(self::PREFIX.$key);
        }
    }

    /**
     * Every setting's value, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return Cache::rememberForever(self::PREFIX.self::SETTINGS, fn (): array => Setting::all()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all());
    }

    /**
     * Active categories with their active sub-categories, for the menus.
     *
     * @return list<array<string, mixed>>
     */
    public static function navCategories(): array
    {
        return Cache::rememberForever(self::PREFIX.self::NAV_CATEGORIES, fn (): array => Category::active()
            ->with(['subCategories' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')->select(['id', 'name', 'slug', 'category_id', 'description'])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'icon', 'color', 'image'])
            ->toArray());
    }

    /**
     * Every homepage section, keyed by section_key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function homepageSettings(): array
    {
        return Cache::rememberForever(self::PREFIX.self::HOMEPAGE_SETTINGS, fn (): array => HomepageSetting::orderBy('sort_order')
            ->get()
            ->keyBy('section_key')
            ->toArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function heroSlides(): array
    {
        return Cache::rememberForever(self::PREFIX.self::HERO_SLIDES, fn (): array => HeroSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function promotionalBanners(): array
    {
        return Cache::rememberForever(self::PREFIX.self::PROMOTIONAL_BANNERS, fn (): array => PromotionalBanner::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray());
    }

    /**
     * @return list<string>
     */
    public static function popularKeywords(): array
    {
        return Cache::rememberForever(self::PREFIX.self::POPULAR_KEYWORDS, fn (): array => PopularKeyword::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('keyword')
            ->all());
    }
}
