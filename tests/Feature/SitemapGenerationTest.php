<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    // GenerateSitemapCommand writes a real file to public_path(); never
    // leave it behind in the working tree after a test run.
    @unlink(public_path('sitemap.xml'));
});

/** @return array<int, string> every <loc> value, namespace-agnostic. */
function sitemapLocs(): array
{
    test()->artisan('sitemap:generate')->assertSuccessful();

    expect(file_exists(public_path('sitemap.xml')))->toBeTrue();

    $xml = new SimpleXMLElement((string) file_get_contents(public_path('sitemap.xml')));

    expect($xml->getName())->toBe('urlset');

    return array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
}

test('the sitemap is well-formed XML with a urlset root', function () {
    $locs = sitemapLocs();

    expect($locs)->not->toBeEmpty();
});

test('the homepage entry carries the priority and change frequency the command sets', function () {
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = new SimpleXMLElement((string) file_get_contents(public_path('sitemap.xml')));

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

    expect($locs)->toContain(url('/about-us'));
    expect(implode(' ', $locs))->not->toContain('/draft-page');
});
