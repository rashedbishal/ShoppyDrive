<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-83: product and category pages carry a BreadcrumbList JSON-LD block
 * that follows the real category hierarchy, printed after (never instead of)
 * the page's main JSON-LD. Production has no SSR, so SSR is forced off.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** @return list<array<string, mixed>> every JSON-LD block on the page, decoded, in page order */
function jsonLdBlocks(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

/** @return list<array{0: string, 1: string}> the page's single BreadcrumbList as [name, url] pairs, positions checked */
function breadcrumbTrail(string $url): array
{
    $lists = array_values(array_filter(jsonLdBlocks($url), fn (array $block): bool => ($block['@type'] ?? null) === 'BreadcrumbList'));

    expect($lists)->toHaveCount(1);
    expect(array_column($lists[0]['itemListElement'], 'position'))->toBe(range(1, count($lists[0]['itemListElement'])));

    return array_map(fn (array $item): array => [$item['name'], $item['item']], $lists[0]['itemListElement']);
}

test('a product page follows its visible trail: home, its category, the product', function () {
    $category = Category::factory()->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
    $product = Product::factory()->create([
        'name' => 'Frying Pan', 'category_id' => $category->id, 'is_active' => true, 'published_at' => now()->subDay(),
    ]);

    expect(breadcrumbTrail(route('product.show', $product->slug)))->toBe([
        ['Home', route('home')],
        ['Kitchen', route('category.show', 'kitchen')],
        ['Frying Pan', route('product.show', $product->slug)],
    ]);
    expect(jsonLdBlocks(route('product.show', $product->slug))[0]['@type'])->toBe('Product');
});

test('a category page is home then the category', function () {
    Category::factory()->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);

    expect(breadcrumbTrail(route('category.show', 'kitchen')))->toBe([
        ['Home', route('home')],
        ['Kitchen', route('category.show', 'kitchen')],
    ]);
});

test('a sub-category page goes through its real parent category', function () {
    $parent = Category::factory()->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
    SubCategory::factory()->create(['name' => 'Pots', 'slug' => 'pots', 'category_id' => $parent->id, 'is_active' => true]);

    expect(breadcrumbTrail(route('category.show', 'pots')))->toBe([
        ['Home', route('home')],
        ['Kitchen', route('category.show', 'kitchen')],
        ['Pots', route('category.show', 'pots')],
    ]);
});

test('a category name with </script> or HTML cannot break out of the breadcrumb block', function () {
    Category::factory()->create(['name' => 'Pans </script><script>alert(1)</script>', 'slug' => 'pans', 'is_active' => true]);

    $html = $this->get(route('category.show', 'pans'))->assertOk()->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(breadcrumbTrail(route('category.show', 'pans'))[1][0])->toBe('Pans </script><script>alert(1)</script>');
});

test('pages outside the catalog hierarchy have no breadcrumb block', function () {
    $types = array_column(jsonLdBlocks(route('home')), '@type');

    expect($types)->not->toContain('BreadcrumbList');
});

test('a brand page is home, all brands, then the brand (DISC-95)', function () {
    Brand::factory()->create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);

    expect(breadcrumbTrail(route('brand.show', 'acme')))->toBe([
        ['Home', route('home')],
        ['Brands', route('brands.index')],
        ['Acme', route('brand.show', 'acme')],
    ]);
});
