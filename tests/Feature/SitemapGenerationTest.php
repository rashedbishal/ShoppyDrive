<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SubCategory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function () {
    // GenerateSitemapCommand writes a real file; never leave it behind
    // after a test run.
    @unlink(GenerateSitemapCommand::path());
});

/** @return array<int, string> every <loc> value, namespace-agnostic. */
function sitemapLocs(): array
{
    test()->artisan('sitemap:generate')->assertSuccessful();

    expect(file_exists(GenerateSitemapCommand::path()))->toBeTrue();

    $xml = new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path()));

    expect($xml->getName())->toBe('urlset');

    return array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
}

test('the sitemap is well-formed XML with a urlset root', function () {
    $locs = sitemapLocs();

    expect($locs)->not->toBeEmpty();
});

test('the homepage entry carries the priority and change frequency the command sets', function () {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path()));

    $priorities = $xml->xpath('//*[local-name()="priority"]');
    $changeFrequencies = $xml->xpath('//*[local-name()="changefreq"]');

    // Only the homepage URL sets these, so finding them at all, with
    // exactly these values, is enough to identify its entry.
    expect($priorities)->toHaveCount(1);
    expect((string) $priorities[0])->toBe('1.0');
    expect($changeFrequencies)->toHaveCount(1);
    expect((string) $changeFrequencies[0])->toBe('daily');
});

test('an active category is included and an inactive one is excluded', function () {
    Category::factory()->create(['slug' => 'active-category', 'is_active' => true]);
    Category::factory()->create(['slug' => 'inactive-category', 'is_active' => false]);

    $locs = sitemapLocs();

    expect($locs)->toContain(url('/category/active-category'));
    expect(implode(' ', $locs))->not->toContain('/category/inactive-category');
});

test('a published, active product is included', function () {
    Product::factory()->create([
        'slug' => 'live-product',
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    $locs = sitemapLocs();

    expect($locs)->toContain(url('/product/live-product'));
});

test('an unpublished, a future-published and an inactive product are all excluded', function () {
    Product::factory()->create(['slug' => 'draft-product', 'is_active' => true, 'published_at' => null]);
    Product::factory()->create(['slug' => 'future-product', 'is_active' => true, 'published_at' => now()->addDay()]);
    Product::factory()->create(['slug' => 'inactive-product', 'is_active' => false, 'published_at' => now()->subDay()]);

    $locs = implode(' ', sitemapLocs());

    expect($locs)->not->toContain('/product/draft-product');
    expect($locs)->not->toContain('/product/future-product');
    expect($locs)->not->toContain('/product/inactive-product');
});

test('a published page is included and an unpublished one is excluded', function () {
    Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>About.</p>', 'is_published' => true]);
    Page::create(['title' => 'Draft Page', 'slug' => 'draft-page', 'content' => '<p>Draft.</p>', 'is_published' => false]);

    $locs = sitemapLocs();

    // Only the legal pages have top-level routes; /about-us would be a 404.
    expect($locs)->toContain(url('/page/about-us'));
    expect(implode(' ', $locs))->not->toContain('/draft-page');
});

test('active brands, published blogs and the main listing pages are included, hidden ones are not', function () {
    Brand::factory()->create(['slug' => 'live-brand', 'is_active' => true]);
    Brand::factory()->create(['slug' => 'hidden-brand', 'is_active' => false]);
    Blog::factory()->create(['slug' => 'live-post', 'is_published' => true, 'published_at' => now()->subDay()]);
    Blog::factory()->create(['slug' => 'draft-post', 'is_published' => false]);

    $locs = sitemapLocs();

    expect($locs)->toContain(url('/brand/live-brand'))
        ->toContain(url('/blog/live-post'))
        ->toContain(url('/products'))
        ->toContain(url('/about'))
        ->toContain(url('/contact'));
    expect(implode(' ', $locs))->not->toContain('hidden-brand')->not->toContain('draft-post');
});

test('an active sub-category under an active category is included, hidden or unreachable ones are not', function () {
    $activeParent = Category::factory()->create(['slug' => 'kitchen', 'is_active' => true]);
    $inactiveParent = Category::factory()->create(['slug' => 'garden', 'is_active' => false]);
    SubCategory::factory()->create(['slug' => 'pots-and-pans', 'category_id' => $activeParent->id, 'is_active' => true]);
    SubCategory::factory()->create(['slug' => 'hidden-sub', 'category_id' => $activeParent->id, 'is_active' => false]);
    SubCategory::factory()->create(['slug' => 'orphan-sub', 'category_id' => $inactiveParent->id, 'is_active' => true]);
    SubCategory::factory()->create(['slug' => 'deleted-sub', 'category_id' => $activeParent->id, 'is_active' => true])->delete();

    $locs = sitemapLocs();

    expect($locs)->toContain(url('/category/pots-and-pans'));
    expect(implode(' ', $locs))->not->toContain('hidden-sub')->not->toContain('orphan-sub')->not->toContain('deleted-sub');
});

test('a sub-category whose slug an active category already uses is not listed a second time', function () {
    $category = Category::factory()->create(['slug' => 'phones', 'is_active' => true]);
    SubCategory::factory()->create(['slug' => 'phones', 'category_id' => $category->id, 'is_active' => true]);

    expect(array_count_values(sitemapLocs())[url('/category/phones')])->toBe(1);
});

test('the flash sale and bundle deal pages are listed', function () {
    expect(sitemapLocs())->toContain(url('/flash-sales'))->toContain(url('/bundle-deals'));
});

test('every legal page in the sitemap uses its real top-level URL', function () {
    $locs = sitemapLocs();

    expect($locs)->toContain(url('/terms'))->toContain(url('/return-policy'))->not->toContain(url('/page/terms'));
});

test('/sitemap.xml serves the generated file as XML, and is 404 until it exists', function () {
    @unlink(GenerateSitemapCommand::path());
    $this->get('/sitemap.xml')->assertNotFound();

    $this->artisan('sitemap:generate')->assertSuccessful();

    $response = $this->get('/sitemap.xml')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->baseResponse->getFile()->getContent())->toContain('<urlset');
});

test('the sitemap is rebuilt nightly and robots.txt points to it', function () {
    expect(collect(app(Schedule::class)->events())->pluck('command')->implode(' '))->toContain('sitemap:generate')
        ->and(file_get_contents(public_path('robots.txt')))->toContain('Sitemap: https://shoppydrive.com/sitemap.xml');
});

/** @return array<string, string|null> each <loc> mapped to its <lastmod> date (Y-m-d), or null when absent. */
function sitemapLastModified(): array
{
    test()->artisan('sitemap:generate')->assertSuccessful();

    $xml = new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path()));
    $dates = [];

    foreach ($xml->xpath('//*[local-name()="url"]') as $url) {
        $loc = (string) $url->xpath('*[local-name()="loc"]')[0];
        $lastmod = $url->xpath('*[local-name()="lastmod"]');
        $dates[$loc] = $lastmod ? substr((string) $lastmod[0], 0, 10) : null;
    }

    return $dates;
}

test('each item is dated by its own last update', function () {
    Product::factory()->create(['slug' => 'dated-product', 'is_active' => true, 'published_at' => now()->subYear(), 'updated_at' => '2026-03-04 10:00:00']);
    Category::factory()->create(['slug' => 'dated-category', 'is_active' => true, 'updated_at' => '2026-02-03 10:00:00']);
    Brand::factory()->create(['slug' => 'dated-brand', 'is_active' => true, 'updated_at' => '2026-05-06 10:00:00']);
    Blog::factory()->create(['slug' => 'dated-post', 'is_published' => true, 'published_at' => now()->subYear(), 'updated_at' => '2026-06-07 10:00:00']);
    Page::where('slug', 'terms')->firstOrFail()->forceFill(['updated_at' => '2026-07-08 10:00:00'])->saveQuietly();

    $dates = sitemapLastModified();

    expect($dates[url('/product/dated-product')])->toBe('2026-03-04')
        ->and($dates[url('/category/dated-category')])->toBe('2026-02-03')
        ->and($dates[url('/brand/dated-brand')])->toBe('2026-05-06')
        ->and($dates[url('/blog/dated-post')])->toBe('2026-06-07')
        ->and($dates[url('/terms')])->toBe('2026-07-08')
        ->and($dates[url('/products')])->toBe('2026-03-04')
        ->and($dates[url('/brands')])->toBe('2026-05-06')
        ->and($dates[url('/blog')])->toBe('2026-06-07');
});

test('the homepage is dated by its newest product or homepage content, and about/contact by their own settings', function () {
    Product::factory()->create(['is_active' => true, 'published_at' => now()->subYear(), 'updated_at' => '2026-03-04 10:00:00']);
    HomepageSetting::query()->update(['updated_at' => '2026-01-02 10:00:00']);
    HeroSlide::create(['title' => 'Sale', 'image' => '/images/slide.webp'])->forceFill(['updated_at' => '2026-09-10 10:00:00'])->saveQuietly();
    Setting::create(['key' => 'about_hero_title', 'value' => 'Hello', 'group' => 'about'])->forceFill(['updated_at' => '2026-04-05 10:00:00'])->saveQuietly();
    Setting::create(['key' => 'contact_phone', 'value' => '+8801700000000', 'group' => 'contact'])->forceFill(['updated_at' => '2026-08-09 10:00:00'])->saveQuietly();
    Setting::create(['key' => 'unrelated_setting', 'value' => 'x', 'group' => 'general'])->forceFill(['updated_at' => '2026-10-11 10:00:00'])->saveQuietly();

    $dates = sitemapLastModified();

    expect($dates[url('/')])->toBe('2026-09-10')
        ->and($dates[url('/about')])->toBe('2026-04-05')
        ->and($dates[url('/contact')])->toBe('2026-08-09');
});

test('a page with nothing to date is listed without a lastmod', function () {
    $dates = sitemapLastModified();

    expect($dates)->toHaveKey(url('/about'))
        ->and($dates[url('/about')])->toBeNull()
        ->and($dates[url('/products')])->toBeNull();
});

test('robots.txt allows search and AI answers but not AI training, for every crawler', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));

    expect($robots)->toMatch('/^User-agent: \*\nContent-Signal: search=yes, ai-input=yes, ai-train=no\nDisallow:\n/m');
});

test('the generated file stays out of public/, where deploys would delete it', function () {
    expect(GenerateSitemapCommand::path())->toStartWith(storage_path('app/private'));
});

/**
 * DISC-97: generates the sitemap and returns, for each product URL, its
 * <image:loc> values in document order (read in the image namespace, so a
 * wrong or missing namespace fails here).
 *
 * @return array<string, list<string>>
 */
function sitemapProductImages(): array
{
    test()->artisan('sitemap:generate')->assertSuccessful();

    $xml = new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path()));
    $images = [];

    foreach ($xml->children('http://www.sitemaps.org/schemas/sitemap/0.9')->url as $url) {
        $loc = (string) $url->loc;

        $images[$loc] = [];

        foreach ($url->children('http://www.google.com/schemas/sitemap-image/1.1')->image as $image) {
            $images[$loc][] = (string) $image->loc;
        }
    }

    return $images;
}

function sitemapProduct(string $slug): Product
{
    return Product::factory()->create(['slug' => $slug, 'is_active' => true, 'published_at' => now()->subDay()]);
}

/** @param array<string, mixed> $attributes */
function sitemapProductMedia(Product $product, string $path, array $attributes = []): void
{
    if (str_starts_with($path, '/storage/')) {
        Storage::disk('public')->put(substr($path, strlen('/storage/')), 'image');
    }

    $product->media()->create(['path' => $path, 'type' => 'image', 'sort_order' => 0, 'is_primary' => false, ...$attributes]);
}

test('a product lists its primary image first, then its gallery images in display order, as absolute URLs', function () {
    Storage::fake('public');
    $product = sitemapProduct('photo-product');
    sitemapProductMedia($product, '/storage/products/third.jpg', ['sort_order' => 3]);
    sitemapProductMedia($product, '/storage/products/first.png', ['sort_order' => 1]);
    sitemapProductMedia($product, '/storage/products/main.webp', ['sort_order' => 2, 'is_primary' => true]);
    sitemapProductMedia($product, '/storage/products/second.jpeg', ['sort_order' => 2]);

    expect(sitemapProductImages()[url('/product/photo-product')])->toBe([
        url('/storage/products/main.webp'),
        url('/storage/products/first.png'),
        url('/storage/products/second.jpeg'),
        url('/storage/products/third.jpg'),
    ]);
});

test('videos, placeholders, external URLs, resized copies, other folders and missing files are left out of a product\'s images', function () {
    Storage::fake('public');
    $product = sitemapProduct('filtered-product');
    sitemapProductMedia($product, '/storage/products/real.jpg', ['is_primary' => true]);
    sitemapProductMedia($product, '/storage/products/clip.mp4');
    sitemapProductMedia($product, '/storage/products/movie.mp4', ['type' => 'video']);
    sitemapProductMedia($product, '/placeholder.svg');
    sitemapProductMedia($product, 'https://cdn.example.com/photo.jpg');
    sitemapProductMedia($product, '/storage/products/real-400w.webp');
    sitemapProductMedia($product, '/storage/categories/banner.jpg');
    $product->media()->create(['path' => '/storage/products/deleted.jpg', 'type' => 'image', 'sort_order' => 1, 'is_primary' => false]);

    expect(sitemapProductImages()[url('/product/filtered-product')])->toBe([url('/storage/products/real.jpg')]);
});

test('an image path saved twice on a product is listed once', function () {
    Storage::fake('public');
    $product = sitemapProduct('duplicate-photo-product');
    sitemapProductMedia($product, '/storage/products/same.jpg', ['is_primary' => true]);
    sitemapProductMedia($product, '/storage/products/same.jpg', ['sort_order' => 1]);

    expect(sitemapProductImages()[url('/product/duplicate-photo-product')])->toBe([url('/storage/products/same.jpg')]);
});

test('a hidden product\'s images never appear, and a product without usable images is still listed without any', function () {
    Storage::fake('public');
    $hidden = Product::factory()->create(['slug' => 'hidden-photo-product', 'is_active' => false, 'published_at' => now()->subDay()]);
    sitemapProductMedia($hidden, '/storage/products/hidden.jpg', ['is_primary' => true]);
    $bare = sitemapProduct('bare-product');
    sitemapProductMedia($bare, '/placeholder.svg', ['is_primary' => true]);

    $images = sitemapProductImages();

    expect($images)->toHaveKey(url('/product/bare-product'))
        ->and($images[url('/product/bare-product')])->toBe([])
        ->and(collect($images)->flatten()->implode(' '))->not->toContain('hidden.jpg');
});

test('product image entries stay valid, escaped XML with no caption, title, change frequency or priority added', function () {
    Storage::fake('public');
    $product = sitemapProduct('escaped-photo-product');
    sitemapProductMedia($product, '/storage/products/tom&jerry.jpg', ['is_primary' => true, 'alt' => 'Tom & Jerry']);

    expect(sitemapProductImages()[url('/product/escaped-photo-product')])->toBe([url('/storage/products/tom&jerry.jpg')]);

    $xml = (string) file_get_contents(GenerateSitemapCommand::path());

    expect($xml)->toContain('tom&amp;jerry.jpg')
        ->not->toContain('image:caption')
        ->not->toContain('image:title')
        ->and(substr_count($xml, '<priority>'))->toBe(1)
        ->and(substr_count($xml, '<changefreq>'))->toBe(1);
});
