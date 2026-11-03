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

    /**
     * FSA-24: the only keys this cache's two consumers (HandleInertiaRequests'
     * shared `settings` prop, and Setting::shippingRateFor()) ever actually
     * read. Deliberately an explicit allow-list, not a blocklist of secret
     * keys — the same table holds courier/gateway/webhook secrets in the
     * same `value` column (see Setting::RICH_TEXT_KEYS for the identical
     * reasoning), and a blocklist could miss a future one. A key left out
     * here is never cached/returned — every reader already falls back to a
     * default for a missing key, so this fails safe, never a crash.
     */
    private const PUBLIC_SETTING_KEYS = [
        'site_name', 'logo', 'currency_symbol', 'contact_email', 'contact_phone',
        'contact_address', 'social_links', 'google_analytics_id', 'meta_pixel_id',
        'whatsapp_number', 'messenger_link', 'referral_enabled',
        'referral_discount_type', 'referral_discount_value',
        'shipping_rate_inside_dhaka', 'shipping_rate_outside_dhaka',
    ];

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget(self::PREFIX.$key);
        }
    }

    /**
     * Every public setting's value, keyed by setting key. Never caches a
     * secret-bearing key (API keys, courier/gateway/webhook credentials) —
     * see PUBLIC_SETTING_KEYS.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return Cache::rememberForever(self::PREFIX.self::SETTINGS, fn (): array => Setting::whereIn('key', self::PUBLIC_SETTING_KEYS)
            ->get()
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
