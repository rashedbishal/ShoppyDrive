<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\PopularKeyword;
use App\Models\PromotionalBanner;
use App\Models\Setting;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Tables whose shared-layout data is served from LayoutCache.
 */
const LAYOUT_CACHED_TABLES = ['settings', 'categories', 'sub_categories', 'homepage_settings', 'hero_slides', 'promotional_banners', 'popular_keywords'];

/**
 * Queries a page load ran against the layout-cached tables.
 *
 * @return list<string>
 */
function layoutTableQueriesDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    return $queries->filter(fn (string $sql) => collect(LAYOUT_CACHED_TABLES)
        ->contains(fn (string $table) => str_contains($sql, "from \"{$table}\"") || str_contains($sql, "from `{$table}`")))
        ->values()
        ->all();
}

function layoutAdmin(): Admin
{
    return createAdmin(['name' => 'Admin', 'email' => 'layout-'.uniqid().'@example.com', 'password' => bcrypt('password')]);
}

test('a repeat page load reads the shared layout data from the cache, not the database', function () {
    Category::factory()->create(['is_active' => true]);
    Setting::create(['key' => 'site_name', 'value' => 'ShoppyDrive', 'group' => 'general']);

    expect(layoutTableQueriesDuring(fn () => $this->get('/order/track')))->not->toBeEmpty();
    expect(layoutTableQueriesDuring(fn () => $this->get('/order/track')))->toBeEmpty();
});

test('cached shared props are identical to what the database holds', function () {
    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);
    SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true, 'name' => 'Sneakers']);
    HeroSlide::create(['title' => 'Big sale', 'image' => '/storage/a.jpg', 'is_active' => true]);
    PromotionalBanner::create(['title' => 'Promo', 'image' => '/storage/b.jpg', 'is_active' => true]);
    PopularKeyword::create(['keyword' => 'shirt', 'is_active' => true, 'sort_order' => 1]);
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 1500]]);

    $this->get('/order/track'); // warm the cache

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('navCategories.0.name', 'Shoes')
        ->where('navCategories.0.sub_categories.0.name', 'Sneakers')
        ->where('hero_slides.0.title', 'Big sale')
        ->where('promotional_banners.0.title', 'Promo')
        ->where('popular_keywords', ['shirt'])
        ->where('homepage_settings.announcement_bar.metadata.free_delivery_amount', 1500)
        ->where('settings.free_delivery_threshold', 1500)
        ->where('settings.shipping_rate_inside_dhaka', 60)
        ->where('settings.shipping_rate_outside_dhaka', 120));
});

// ── Every kind of admin change shows up on the very next page load ───────────

test('saving a setting takes effect immediately, including delivery rates used at checkout', function () {
    $this->get('/order/track');
    expect(Setting::shippingRateFor(null, null))->toBe(120.0);

    Setting::updateOrCreate(['key' => 'shipping_rate_outside_dhaka'], ['value' => 150, 'group' => 'shipping']);
    Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'New Name', 'group' => 'general']);

    expect(Setting::shippingRateFor(null, null))->toBe(150.0);
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('settings.site_name', 'New Name')
        ->where('settings.shipping_rate_outside_dhaka', 150));
});

test('category and sub-category edits, deletes and restores update the menu immediately', function () {
    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true, 'name' => 'Sneakers']);
    $this->get('/order/track');

    $category->update(['name' => 'Footwear']);
    $subCategory->update(['name' => 'Trainers']);
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('navCategories.0.name', 'Footwear')
        ->where('navCategories.0.sub_categories.0.name', 'Trainers'));

    $category->delete();
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('navCategories', []));

    $category->restore();
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('navCategories.0.name', 'Footwear'));
});

test('reordering categories (a bulk update) updates the menu immediately', function () {
    $first = Category::factory()->create(['is_active' => true, 'name' => 'First', 'sort_order' => 0]);
    $second = Category::factory()->create(['is_active' => true, 'name' => 'Second', 'sort_order' => 1]);
    $this->get('/order/track');

    actingAs(layoutAdmin(), 'admin')->post(route('admin.categories.reorder'), ['ids' => [$second->id, $first->id]]);
    auth('admin')->logout(); // admins are redirected away from storefront pages

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('navCategories.0.name', 'Second'));
});

test('reordering homepage sections (a bulk update) takes effect immediately', function () {
    $a = HomepageSetting::updateOrCreate(['section_key' => 'new_arrivals'], ['section_name' => 'A', 'sort_order' => 0]);
    $b = HomepageSetting::updateOrCreate(['section_key' => 'best_sellers'], ['section_name' => 'B', 'sort_order' => 1]);
    $this->get('/order/track');

    actingAs(layoutAdmin(), 'admin')->post(route('admin.homepage.reorder'), ['ids' => [$b->id, $a->id]]);
    auth('admin')->logout(); // admins are redirected away from storefront pages

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('homepage_settings.best_sellers.sort_order', 0)
        ->where('homepage_settings.new_arrivals.sort_order', 1));
});

test('bulk-deleting popular keywords takes effect immediately', function () {
    $keyword = PopularKeyword::create(['keyword' => 'shirt', 'is_active' => true]);
    $this->get('/order/track');

    actingAs(layoutAdmin(), 'admin')->delete(route('admin.popular-keywords.destroyMany'), ['ids' => [$keyword->id]]);
    auth('admin')->logout(); // admins are redirected away from storefront pages

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('popular_keywords', []));
});

test('hero slides, promo banners and keywords appear and disappear immediately', function () {
    $this->get('/order/track');

    $slide = HeroSlide::create(['title' => 'Slide', 'image' => '/storage/a.jpg', 'is_active' => true]);
    $banner = PromotionalBanner::create(['title' => 'Banner', 'image' => '/storage/b.jpg', 'is_active' => true]);
    PopularKeyword::create(['keyword' => 'saree', 'is_active' => true]);
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('hero_slides.0.title', 'Slide')
        ->where('promotional_banners.0.title', 'Banner')
        ->where('popular_keywords', ['saree']));

    $slide->update(['is_active' => false]);
    $banner->delete();
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('hero_slides', [])
        ->where('promotional_banners', []));
});

test('cached layout data survives a real persistent cache store between requests', function () {
    // The in-memory test store never serializes, so it can't catch data that
    // breaks when read back from disk (e.g. model objects, which come back as
    // __PHP_Incomplete_Class because cache.serializable_classes is false).
    $directory = sys_get_temp_dir().'/layout-cache-test-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory]);
    Cache::purge('file');

    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);
    SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true, 'name' => 'Sneakers']);
    HeroSlide::create(['title' => 'Big sale', 'image' => '/storage/a.jpg', 'is_active' => true]);
    PromotionalBanner::create(['title' => 'Promo', 'image' => '/storage/b.jpg', 'is_active' => true]);
    PopularKeyword::create(['keyword' => 'shirt', 'is_active' => true]);
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 1500]]);
    Setting::create(['key' => 'site_name', 'value' => 'ShoppyDrive', 'group' => 'general']);

    try {
        foreach ([1, 2] as $ignored) {
            $this->get('/order/track')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('navCategories.0.sub_categories.0.name', 'Sneakers')
                ->where('hero_slides.0.title', 'Big sale')
                ->where('promotional_banners.0.title', 'Promo')
                ->where('popular_keywords', ['shirt'])
                ->where('settings.site_name', 'ShoppyDrive')
                ->where('settings.free_delivery_threshold', 1500));
        }

        expect(Setting::shippingRateFor(null, null))->toBe(120.0);
    } finally {
        File::deleteDirectory($directory);
    }
});
