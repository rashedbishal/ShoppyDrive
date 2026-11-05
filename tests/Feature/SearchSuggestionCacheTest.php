<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SubCategory;
use App\Support\SearchSuggestionCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

function suggest(string $query): TestResponse
{
    return test()->getJson('/api/search/suggestions?q='.urlencode($query))->assertOk();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function searchableProduct(array $attributes = []): Product
{
    return Product::factory()->create(['is_active' => true, 'is_featured' => false, 'published_at' => now()->subDay(), ...$attributes]);
}

/**
 * How many queries a request ran against the tables suggestions read.
 */
function suggestionQueriesDuring(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = collect(DB::getQueryLog())
        ->filter(fn (array $q) => preg_match('/from "(products|brands|categories|sub_categories|product_media)"/', $q['query']))
        ->count();
    DB::disableQueryLog();

    return $count;
}

/**
 * The fields the admin homepage-section form always submits.
 *
 * @return array<string, mixed>
 */
function homepageForm(): array
{
    return ['title' => null, 'description' => null, 'eyebrow' => null, 'action_label' => null, 'action_href' => null, 'is_visible' => true];
}

function suggestionAdmin(): Admin
{
    return createAdmin(['name' => 'Admin', 'email' => 'suggest-'.uniqid().'@example.com', 'password' => bcrypt('password')]);
}

// ── Caching itself ───────────────────────────────────────────────────────────

test('a repeated search is answered from the cache with an identical response', function () {
    searchableProduct(['name' => 'Cotton Shirt']);
    Brand::factory()->create(['name' => 'Shirtmakers']);

    $first = suggest('shirt')->json();

    expect(suggestionQueriesDuring(fn () => expect(suggest('shirt')->json())->toBe($first)))->toBe(0);
});

test('different spacing and case share one cache entry', function () {
    searchableProduct(['name' => 'Red Shirt']);
    $first = suggest('red shirt')->json();

    foreach (['red  shirt', '  red shirt  ', 'RED Shirt'] as $variant) {
        expect(suggestionQueriesDuring(fn () => expect(suggest($variant)->json())->toBe($first)))->toBe(0);
    }
});

test('searches that match nothing are never stored', function () {
    searchableProduct(['name' => 'Cotton Shirt']);

    suggest('zzzqqq');
    expect(suggestionQueriesDuring(fn () => suggest('zzzqqq')))->toBeGreaterThan(0);
});

test('a very long search is cut to the limit, so repeats come from the cache', function () {
    $long = str_repeat('cotton shirt ', 7);
    searchableProduct(['name' => trim($long)]);

    suggest($long);

    expect(suggestionQueriesDuring(fn () => suggest($long)))->toBe(0);
});

test('cached suggestions expire after a few minutes, so a scheduled product appears on time', function () {
    searchableProduct(['name' => 'Cotton Shirt']);
    $scheduled = searchableProduct(['name' => 'Linen Shirt', 'published_at' => now()->addMinutes(2)]);

    expect(collect(suggest('shirt')->json('products'))->pluck('id'))->not->toContain($scheduled->id);

    $this->travel(SearchSuggestionCache::TTL_SECONDS + 1)->seconds();

    expect(collect(suggest('shirt')->json('products'))->pluck('id'))->toContain($scheduled->id);
});

// ── Every relevant change shows up on the very next search ───────────────────

test('product name, price, deactivation, deletion and restore show up immediately', function () {
    $product = searchableProduct(['name' => 'Cotton Shirt', 'price' => 500]);
    suggest('shirt');

    $product->update(['price' => 450]);
    expect(suggest('shirt')->json('products.0.price'))->toEqual('450.00');

    $product->update(['name' => 'Cotton Tee']);
    expect(suggest('shirt')->json('products'))->toBe([]);
    expect(suggest('tee')->json('products.0.name'))->toBe('Cotton Tee');

    $product->update(['is_active' => false]);
    expect(suggest('tee')->json('products'))->toBe([]);

    $product->update(['is_active' => true]);
    suggest('tee');
    $product->delete();
    expect(suggest('tee')->json('products'))->toBe([]);

    $product->restore();
    expect(suggest('tee')->json('products.0.id'))->toBe($product->id);
});

test('a stock change from an order does not clear the cache', function () {
    $product = searchableProduct(['name' => 'Cotton Shirt', 'stock_quantity' => 10]);
    suggest('shirt');

    $product->decrement('stock_quantity');
    $product->update(['stock_quantity' => 5]);

    expect(suggestionQueriesDuring(fn () => suggest('shirt')))->toBe(0);
});

test('a product image change shows up immediately', function () {
    $product = searchableProduct(['name' => 'Cotton Shirt']);
    suggest('shirt');

    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/new.jpg', 'type' => 'image', 'sort_order' => 0]);

    expect(suggest('shirt')->json('products.0.images'))->toContain(['path' => '/storage/new.jpg']);
});

test('brand, category and sub-category changes show up immediately', function () {
    $brand = Brand::factory()->create(['name' => 'Shirtmakers']);
    $category = Category::factory()->create(['name' => 'Shirts', 'is_active' => true]);
    SubCategory::factory()->create(['name' => 'Formal Shirts', 'category_id' => $category->id, 'is_active' => true]);
    suggest('shirt');

    $brand->update(['is_active' => false]);
    $category->update(['name' => 'Shirts & Tops']);

    $response = suggest('shirt');
    expect($response->json('brands'))->toBe([])
        ->and($response->json('categories.0.name'))->toBe('Shirts & Tops')
        ->and($response->json('subcategories.0.category_name'))->toBe('Shirts & Tops');
});

// Each bulk action is tested on its own: run together, one action's cache
// clear would mask another's missing one.

test('the homepage featured-products change (bulk update) shows up immediately', function () {
    searchableProduct(['name' => 'Plain Shirt']);
    $star = searchableProduct(['name' => 'Star Shirt']);
    $featured = HomepageSetting::updateOrCreate(['section_key' => 'featured_products'], ['section_name' => 'Featured']);
    suggest('shirt');

    actingAs(suggestionAdmin(), 'admin')
        ->put(route('admin.homepage.update', $featured), [...homepageForm(), 'featured_products' => [['id' => $star->id]]])
        ->assertSessionHasNoErrors();

    expect(suggest('shirt')->json('products.0.id'))->toBe($star->id);
});

test('the homepage brands toggle (bulk update) shows up immediately', function () {
    $brand = Brand::factory()->create(['name' => 'Shirtmakers']);
    $brands = HomepageSetting::updateOrCreate(['section_key' => 'brands'], ['section_name' => 'Brands']);
    expect(suggest('shirt')->json('brands.0.id'))->toBe($brand->id);

    actingAs(suggestionAdmin(), 'admin')
        ->put(route('admin.homepage.update', $brands), [...homepageForm(), 'section_brands' => []])
        ->assertSessionHasNoErrors();

    expect(suggest('shirt')->json('brands'))->toBe([]);
});

test('suggestions survive a real persistent cache store between requests', function () {
    $directory = sys_get_temp_dir().'/suggestion-cache-test-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory]);
    Cache::purge('file');

    $product = searchableProduct(['name' => 'Cotton Shirt']);
    $category = Category::factory()->create(['name' => 'Shirts', 'is_active' => true]);
    SubCategory::factory()->create(['name' => 'Formal Shirts', 'category_id' => $category->id, 'is_active' => true]);

    try {
        $first = suggest('shirt')->json();
        expect(suggest('shirt')->json())->toBe($first)
            ->and($first['products'][0]['id'])->toBe($product->id)
            ->and($first['subcategories'][0]['category_name'])->toBe('Shirts');
    } finally {
        File::deleteDirectory($directory);
    }
});
