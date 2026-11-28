<?php

declare(strict_types=1);

use App\Http\Controllers\ProductListingController;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SubCategory;
use App\Support\PageSeo;
use Inertia\Testing\AssertableInertia;

/*
 * The 2026-11-28 SEO specialist batch: fallback <h1> without the site-name
 * suffix (DISC-101), real /products and default copy (DISC-102), the
 * product breadcrumb's sub-category (DISC-103) and a category share image
 * taken from its own products (DISC-104).
 */

function storefrontNoscript(string $html): string
{
    // The agent fallback's block (partials/agent-fallback), not the boot loader's.
    preg_match_all('#<noscript>(.*?)</noscript>#s', $html, $blocks);

    return collect($blocks[1])->first(fn (string $block): bool => str_contains($block, '<header>')) ?? '';
}

function storefrontVisibleProduct(array $overrides = []): Product
{
    return Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'stock_quantity' => 4,
        ...$overrides,
    ]);
}

/** @return list<array<string, mixed>> */
function storefrontJsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

    return array_map(fn (string $json): array => json_decode($json, true), $blocks[1]);
}

// ── DISC-101: the fallback <h1> names the page's subject ────────────────────

test('the no-JavaScript h1 is the subject without the site-name suffix the title keeps', function (Closure $makeUrl, string $heading, string $title) {
    $html = $this->get($makeUrl())->assertOk()->getContent();

    expect(storefrontNoscript($html))->toContain('<h1>'.e($heading).'</h1>')
        ->not->toContain(' - '.config('app.name').'</h1>')
        ->and($html)->toContain('<title>'.e($title.' - '.config('app.name')).'</title>');
})->with([
    'product (name, even with an SEO title)' => [fn () => route('product.show', storefrontVisibleProduct(['name' => 'Cute Cat Night Light', 'meta_title' => 'Cute Cat Night Light Lamp | Bangladesh'])->slug), 'Cute Cat Night Light', 'Cute Cat Night Light Lamp | Bangladesh'],
    'category (name, even with an SEO title)' => [fn () => route('category.show', Category::factory()->create(['name' => 'Toys & Games', 'slug' => 'toys-games', 'meta_title' => 'Toys & Games Online in Bangladesh', 'is_active' => true])->slug), 'Toys & Games', 'Toys & Games Online in Bangladesh'],
    'sub-category' => [fn () => route('category.show', SubCategory::factory()->create(['name' => 'Fidget Toys', 'slug' => 'fidget-toys', 'is_active' => true, 'category_id' => Category::factory()->create(['is_active' => true])->id])->slug), 'Fidget Toys', 'Fidget Toys'],
    'brand' => [fn () => route('brand.show', Brand::factory()->create(['name' => 'Tomi', 'slug' => 'tomi-brand', 'meta_title' => 'Tomi Watches in Bangladesh', 'is_active' => true])->slug), 'Tomi', 'Tomi Watches in Bangladesh'],
    'blog post' => [fn () => route('blog.show', Blog::factory()->create(['title' => 'Choosing a Night Light', 'meta_title' => 'How to Choose a Night Light', 'is_published' => true, 'published_at' => now()->subDay()])->slug), 'Choosing a Night Light', 'How to Choose a Night Light'],
    'all products' => [fn () => route('products.index'), 'All products', ProductListingController::ALL_PRODUCTS_TITLE],
]);

test('the page heading helper falls back to the site name when blank', function () {
    expect(PageSeo::heading('  Fidget Toys  '))->toBe('Fidget Toys')
        ->and(PageSeo::heading(''))->toBe(config('app.name'))
        ->and(PageSeo::heading(null))->toBe(config('app.name'));
});

// ── DISC-102: /products and the site default describe the real catalogue ───

test('/products has its own catalogue title and description in the first HTML', function () {
    $html = $this->get(route('products.index'))->assertOk()->getContent();

    expect($html)->toContain('<title>'.e(ProductListingController::ALL_PRODUCTS_TITLE.' - '.config('app.name')).'</title>')
        ->toContain('name="description" content="'.e(ProductListingController::ALL_PRODUCTS_DESCRIPTION).'"')
        ->toContain('rel="canonical" href="'.route('products.index').'"')
        ->and(mb_strlen(ProductListingController::ALL_PRODUCTS_DESCRIPTION))->toBeLessThanOrEqual(160);
});

test('the site default description names what the shop sells and no longer promises electronics', function () {
    expect(PageSeo::DEFAULT_DESCRIPTION)->not->toContain('electronics')
        ->toContain('watches')->toContain('night lights')->toContain('toys')->toContain('gifts')->toContain('Bangladesh')
        ->and(mb_strlen(PageSeo::DEFAULT_DESCRIPTION))->toBeLessThanOrEqual(160);

    expect($this->get(route('home'))->getContent())->toContain('name="description" content="'.e(PageSeo::DEFAULT_DESCRIPTION).'"');
});

// ── DISC-103: product breadcrumb includes its sub-category ──────────────────

test('a product in a sub-category shows Home › category › sub-category › product, visibly and in BreadcrumbList', function () {
    $category = Category::factory()->create(['name' => 'Toys & Games', 'slug' => 'toys-games', 'is_active' => true]);
    $subCategory = SubCategory::factory()->create(['name' => 'Fidget Toys', 'slug' => 'fidget-toys', 'category_id' => $category->id, 'is_active' => true]);
    $product = storefrontVisibleProduct(['name' => 'Press & Twist Decompression Ball', 'category_id' => $category->id, 'sub_category_id' => $subCategory->id]);

    $response = $this->get(route('product.show', $product->slug))->assertOk();
    $trail = collect(storefrontJsonLd($response->getContent()))->firstWhere('@type', 'BreadcrumbList')['itemListElement'];

    expect(array_column($trail, 'name'))->toBe(['Home', 'Toys & Games', 'Fidget Toys', 'Press & Twist Decompression Ball'])
        ->and(array_column($trail, 'item'))->toBe([route('home'), route('category.show', 'toys-games'), route('category.show', 'fidget-toys'), route('product.show', $product->slug)])
        ->and(array_column($trail, 'position'))->toBe([1, 2, 3, 4])
        ->and(substr_count($response->getContent(), '"@type":"BreadcrumbList"'))->toBe(1);

    // The visible breadcrumb (ProductDetail.tsx) is built from the same records.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('product.category.slug', 'toys-games')
        ->where('product.sub_category.slug', 'fidget-toys')
        ->where('product.sub_category.category_id', $category->id));
    expect((string) file_get_contents(resource_path('js/pages/catalog/ProductDetail.tsx')))
        ->toContain('product.sub_category.category_id === product.category.id');
});

test('a product without a sub-category keeps the three-level trail', function () {
    $category = Category::factory()->create(['name' => 'Home & Living', 'slug' => 'home-living', 'is_active' => true]);
    $product = storefrontVisibleProduct(['name' => 'Cute Cat Night Light', 'category_id' => $category->id, 'sub_category_id' => null]);

    $trail = collect(storefrontJsonLd($this->get(route('product.show', $product->slug))->getContent()))->firstWhere('@type', 'BreadcrumbList')['itemListElement'];

    expect(array_column($trail, 'name'))->toBe(['Home', 'Home & Living', 'Cute Cat Night Light']);
});

// ── DISC-104: a category's share image comes from its own products ──────────

test('a category without its own picture shares the photo of the product it lists first', function () {
    $category = Category::factory()->create(['slug' => 'home-living', 'image' => '/placeholder.svg', 'is_active' => true]);
    $product = storefrontVisibleProduct(['category_id' => $category->id, 'is_featured' => true]);
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/products/cat-lamp.jpg', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);

    expect($this->get(route('category.show', 'home-living'))->getContent())
        ->toContain('property="og:image" content="'.url('/storage/products/cat-lamp.jpg').'"');
});

test('a category keeps its own picture, and with no picture anywhere falls back to the site default', function () {
    $withImage = Category::factory()->create(['slug' => 'toys-games', 'image' => '/storage/categories/toys.png', 'is_active' => true]);
    $product = storefrontVisibleProduct(['category_id' => $withImage->id]);
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/products/ball.png', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);
    Category::factory()->create(['slug' => 'gift-items', 'image' => null, 'is_active' => true]);

    expect($this->get(route('category.show', 'toys-games'))->getContent())->toContain('property="og:image" content="'.url('/storage/categories/toys.png').'"')
        ->and($this->get(route('category.show', 'gift-items'))->getContent())->toContain('property="og:image" content="'.url('/images/brand/og-image.jpg').'"');
});
