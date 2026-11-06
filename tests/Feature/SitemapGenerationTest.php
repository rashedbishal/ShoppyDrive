<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('the generated file stays out of public/, where deploys would delete it', function () {
    expect(GenerateSitemapCommand::path())->toStartWith(storage_path('app/private'));
});
