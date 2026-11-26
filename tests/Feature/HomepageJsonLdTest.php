<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-61: the homepage tells search engines and AI agents what business
 * this is, from saved Admin › Settings values only. Production has no SSR,
 * so the Blade root view's first response is what they read.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** @return array{0: string, 1: array<string, mixed>} the page HTML and its decoded JSON-LD. */
function homepageJsonLd(): array
{
    $html = test()->get(route('home'))->assertOk()->getContent();

    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match))->toBe(1);

    return [$html, json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)];
}

function saveSetting(string $key, mixed $value): void
{
    Setting::create(['key' => $key, 'value' => $value, 'group' => 'general']);
}

test('the homepage describes the business and the site from saved settings', function () {
    saveSetting('site_name', 'ShoppyDrive');
    saveSetting('legal_business_name', 'ShoppyDrive Ltd.');
    saveSetting('logo', '/storage/settings/logo.png');
    saveSetting('contact_email', 'help@example.com');
    saveSetting('contact_phone', '+8801711000000');
    saveSetting('contact_address', 'House 1, Road 2, Dhaka');
    saveSetting('social_links', [
        'facebook' => 'https://facebook.com/shoppydrive',
        'instagram' => '',
        'youtube' => 'https://youtube.com/@shoppydrive',
        'tiktok' => 'not-a-url',
    ]);

    [, $jsonLd] = homepageJsonLd();
    [$organization, $website] = $jsonLd['@graph'];

    expect($jsonLd['@context'])->toBe('https://schema.org');
    expect($organization)->toBe([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => 'ShoppyDrive',
        'legalName' => 'ShoppyDrive Ltd.',
        'url' => url('/'),
        'logo' => url('/storage/settings/logo.png'),
        'email' => 'help@example.com',
        'telephone' => '+8801711000000',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'House 1, Road 2, Dhaka', 'addressCountry' => 'BD'],
        'sameAs' => ['https://facebook.com/shoppydrive', 'https://youtube.com/@shoppydrive'],
    ]);
    expect($website['@type'])->toBe('WebSite');
    expect($website['publisher'])->toBe(['@id' => url('/').'#organization']);
    expect($website['potentialAction']['target']['urlTemplate'])->toBe(url('/search').'?q={search_term_string}');
});

test('unsaved contact details are left out instead of the storefront placeholders, and the page keeps its own title', function () {
    [$html, $jsonLd] = homepageJsonLd();

    expect($jsonLd['@graph'][0])->toBe([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => config('app.name'),
        'url' => url('/'),
    ]);
    expect($html)->toContain('<title>Online Shopping in Bangladesh - '.config('app.name').'</title>');
});

test('a business name with </script> or HTML cannot break out of the JSON-LD script', function () {
    $name = 'Shop "A" & B </script><script>alert(1)</script>';
    saveSetting('site_name', $name);

    [$html, $jsonLd] = homepageJsonLd();

    expect($html)->not->toContain('<script>alert(1)</script>');
    expect($jsonLd['@graph'][0]['name'])->toBe($name);
});

function showHomepageSection(string $section, bool $visible = true): void
{
    HomepageSetting::updateOrCreate(['section_key' => $section], ['section_name' => $section, 'is_visible' => $visible, 'sort_order' => 0]);
}

/** @return array<string, mixed>|null the ItemList node, if any. */
function homepageProductList(): ?array
{
    [, $jsonLd] = homepageJsonLd();

    return collect($jsonLd['@graph'])->firstWhere('@type', 'ItemList');
}

test('DISC-64: products in a visible section are listed with the price, currency and stock their card shows', function () {
    showHomepageSection('featured_products');
    Product::factory()->create([
        'name' => 'Steel Kettle', 'slug' => 'steel-kettle', 'sku' => 'KET-1', 'price' => 1500, 'stock_quantity' => 4,
        'is_active' => true, 'is_featured' => true, 'published_at' => now()->subDay(),
    ]);

    $list = homepageProductList();

    expect($list['numberOfItems'])->toBe(1);
    expect($list['itemListElement'][0]['position'])->toBe(1);
    expect($list['itemListElement'][0]['item'])->toMatchArray([
        '@type' => 'Product',
        'name' => 'Steel Kettle',
        'url' => url('/product/steel-kettle'),
        'sku' => 'KET-1',
        'offers' => [
            '@type' => 'Offer',
            'url' => url('/product/steel-kettle'),
            'priceCurrency' => 'BDT',
            'price' => '1500.00',
            'availability' => 'https://schema.org/InStock',
        ],
    ]);
});

test('DISC-64: a product on an active flash sale is listed once, at its flash-sale price', function () {
    showHomepageSection('flash_sale');
    showHomepageSection('featured_products');
    $product = Product::factory()->create([
        'slug' => 'sale-kettle', 'price' => 1500, 'stock_quantity' => 0,
        'is_active' => true, 'is_featured' => true, 'published_at' => now()->subDay(),
    ]);
    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => null, 'sale_price' => 1000]);

    $list = homepageProductList();

    expect($list['numberOfItems'])->toBe(1);
    expect($list['itemListElement'][0]['item']['offers'])->toMatchArray([
        'price' => '1000.00',
        'availability' => 'https://schema.org/OutOfStock',
    ]);
});

test('DISC-64: products of a hidden section are not listed', function () {
    showHomepageSection('featured_products', visible: false);
    Product::factory()->create(['is_active' => true, 'is_featured' => true, 'published_at' => now()->subDay()]);

    expect(homepageProductList())->toBeNull();
});
