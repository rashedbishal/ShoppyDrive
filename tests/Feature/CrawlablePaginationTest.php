<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Inertia\Testing\AssertableInertia;

/*
 * SEO-AUDIT-01: crawlers follow links, never button clicks, so the shared
 * storefront pagination must render real links to the `?page=N` URLs the
 * server already paginates, and those URLs must serve that page with the
 * listing's filters intact.
 */

function paginationComponentSource(): string
{
    return (string) file_get_contents(resource_path('js/components/ui/pagination.tsx'));
}

test('the pagination renders previous, next and page numbers as links to the current URL with only page changed', function () {
    $source = paginationComponentSource();

    // Page numbers and enabled Prev/Next are anchors with an href...
    expect(substr_count($source, 'href={pageHref(currentUrl, '))->toBe(3)
        ->and($source)->toContain('href={pageHref(currentUrl, item)}')
        ->and($source)->toContain('href={pageHref(currentUrl, previousPage)}')
        ->and($source)->toContain('href={pageHref(currentUrl, nextPage)}')
        // ...built from the current URL, keeping its other query parameters,
        // with page 1 linking to the plain URL (the server's canonical).
        ->and($source)->toContain('url.searchParams.set("page", String(page))')
        ->and($source)->toContain('url.searchParams.delete("page")')
        ->and($source)->toContain('const { url: currentUrl } = usePage();')
        // A page button without a link is gone; only the disabled Prev/Next stay buttons.
        ->and($source)->not->toContain('onClick={() => onPageChange(item)}')
        ->and(substr_count($source, '<button'))->toBe(2)
        ->and(substr_count($source, '<button type="button" disabled'))->toBe(2);
});

test('a plain click keeps the in-app visit, while modified or middle clicks are left to the browser', function () {
    $source = paginationComponentSource();

    expect(substr_count($source, 'onClick={(event) => followInApp(event, '))->toBe(3)
        ->and($source)->toContain('event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey')
        ->and($source)->toContain('event.preventDefault();')
        ->and($source)->toContain('onPageChange(page);')
        ->and($source)->toContain('aria-current={item === data.current_page ? "page" : undefined}');
});

test('the linked page URLs serve that page, with the listing filters kept', function (Closure $url, string $prop) {
    $category = Category::factory()->create(['slug' => 'toys', 'is_active' => true]);
    $subCategory = SubCategory::factory()->create(['slug' => 'plush', 'category_id' => $category->id, 'is_active' => true]);
    $brand = Brand::factory()->create(['slug' => 'acme', 'is_active' => true]);
    Product::factory()->count(13)->create(['category_id' => $category->id, 'sub_category_id' => $subCategory->id, 'brand_id' => $brand->id, 'is_active' => true, 'stock_quantity' => 5]);
    Blog::factory()->count(10)->create(['is_published' => true, 'published_at' => now()->subDay()]);

    $this->get($url())
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("{$prop}.current_page", 2)
            ->where("{$prop}.last_page", 2)
            ->where("{$prop}.first_page_url", fn (string $first) => ! str_contains($url(), 'sort=') || str_contains($first, 'sort=price_asc')));
})->with([
    'all products' => [fn (): string => '/products?page=2', 'products'],
    'all products, sorted' => [fn (): string => '/products?sort=price_asc&page=2', 'products'],
    'category' => [fn (): string => '/category/toys?page=2', 'products'],
    'sub-category' => [fn (): string => '/category/plush?page=2', 'products'],
    'brand' => [fn (): string => '/brand/acme?page=2', 'products'],
    'blog' => [fn (): string => '/blog?page=2', 'blogs'],
]);
