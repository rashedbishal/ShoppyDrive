<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $attributes
 */
function listedProduct(array $attributes = []): Product
{
    return Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), ...$attributes]);
}

/**
 * The filter-sidebar props of a fresh /products load.
 *
 * @return array<string, mixed>
 */
function filterProps(): array
{
    $props = [];
    test()->get('/products')->assertOk()->assertInertia(function (Assert $page) use (&$props) {
        $props = collect($page->toArray()['props'])->only(['categories', 'brands', 'attributes', 'maxPrice'])->all();
    });

    return $props;
}

function filterQueriesDuring(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'as "products_count"') || str_contains($sql, 'from "attributes"') || str_contains($sql, 'max("price")'))
        ->count();
    DB::disableQueryLog();

    return $count;
}

function catalogAdmin(): Admin
{
    return Admin::create(['name' => 'Admin', 'email' => 'catalog-'.uniqid().'@example.com', 'password' => bcrypt('password')]);
}

function brandCount(array $props, Brand $brand): ?int
{
    return collect($props['brands'])->firstWhere('id', $brand->id)['products_count'] ?? null;
}

// ── Caching itself ───────────────────────────────────────────────────────────

test('a repeat listing load serves the filter options from the cache', function () {
    $category = Category::factory()->create(['is_active' => true]);
    listedProduct(['category_id' => $category->id]);

    $first = filterProps();

    expect(filterQueriesDuring(fn () => expect(filterProps())->toBe($first)))->toBe(0);
});

test('a stock change from an order does not clear the cache', function () {
    $product = listedProduct(['stock_quantity' => 10]);
    filterProps();

    $product->decrement('stock_quantity');
    $product->update(['stock_quantity' => 3]);

    expect(filterQueriesDuring(fn () => filterProps()))->toBe(0);
});

test('a scheduled product is counted exactly when it goes live, not up to an hour later', function () {
    $brand = Brand::factory()->create();
    listedProduct(['brand_id' => $brand->id, 'price' => 100]);
    listedProduct(['brand_id' => $brand->id, 'price' => 900, 'published_at' => now()->addSeconds(90)]);

    $before = filterProps();
    expect(brandCount($before, $brand))->toBe(1)->and($before['maxPrice'])->toBe(100);

    $this->travel(91)->seconds();

    $after = filterProps();
    expect(brandCount($after, $brand))->toBe(2)->and($after['maxPrice'])->toBe(900);
});

// ── Every relevant change shows up on the very next load ─────────────────────

test('product changes update counts and the highest price immediately', function () {
    $brand = Brand::factory()->create();
    $otherBrand = Brand::factory()->create();
    $product = listedProduct(['brand_id' => $brand->id, 'price' => 100]);
    filterProps();

    $product->update(['price' => 2500]);
    expect(filterProps()['maxPrice'])->toBe(2500);

    $product->update(['brand_id' => $otherBrand->id]);
    $props = filterProps();
    expect(brandCount($props, $brand))->toBe(0)->and(brandCount($props, $otherBrand))->toBe(1);

    $product->update(['is_active' => false]);
    expect(brandCount(filterProps(), $otherBrand))->toBe(0);

    $product->update(['is_active' => true]);
    filterProps();
    $product->delete();
    expect(brandCount(filterProps(), $otherBrand))->toBe(0);

    $product->restore();
    expect(brandCount(filterProps(), $otherBrand))->toBe(1);

    listedProduct(['brand_id' => $otherBrand->id]);
    expect(brandCount(filterProps(), $otherBrand))->toBe(2);
});

test('category, sub-category and brand edits show up immediately', function () {
    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);
    $brand = Brand::factory()->create(['name' => 'Acme']);
    filterProps();

    $category->update(['name' => 'Footwear']);
    SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true, 'name' => 'Sneakers']);
    $brand->update(['name' => 'Acme Co']);

    $props = filterProps();
    $listed = collect($props['categories'])->firstWhere('id', $category->id);
    expect($listed['name'])->toBe('Footwear')
        ->and($listed['sub_categories'][0]['name'])->toBe('Sneakers')
        ->and(collect($props['brands'])->firstWhere('id', $brand->id)['name'])->toBe('Acme Co');

    $category->update(['is_active' => false]);
    expect(collect(filterProps()['categories'])->firstWhere('id', $category->id))->toBeNull();
});

test('new colours and sizes show up immediately', function () {
    $color = Attribute::firstWhere('name', 'Color');
    filterProps();

    AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Teal', 'color_hex' => '#008080']);

    $values = collect(filterProps()['attributes'])->firstWhere('id', $color->id)['values'];
    expect(collect($values)->pluck('value'))->toContain('Teal');
});

// Each bulk action is tested on its own: run together, one action's cache
// clear would mask another's missing one.

test('a category reorder (bulk update) shows up immediately', function () {
    $first = Category::factory()->create(['is_active' => true, 'sort_order' => 0]);
    $second = Category::factory()->create(['is_active' => true, 'sort_order' => 1]);
    filterProps();

    actingAs(catalogAdmin(), 'admin')->post(route('admin.categories.reorder'), ['ids' => [$second->id, $first->id]]);
    auth('admin')->logout();

    expect(collect(filterProps()['categories'])->pluck('id')->take(2)->all())->toBe([$second->id, $first->id]);
});

test('a colour/size reorder (bulk update) shows up immediately', function () {
    $color = Attribute::firstWhere('name', 'Color');
    $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'color_hex' => '#ff0000', 'sort_order' => 0]);
    $blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue', 'color_hex' => '#0000ff', 'sort_order' => 1]);
    filterProps();

    actingAs(catalogAdmin(), 'admin')->post(route('admin.attribute-values.reorder'), ['ids' => [$blue->id, $red->id]]);
    auth('admin')->logout();

    $values = collect(collect(filterProps()['attributes'])->firstWhere('id', $color->id)['values'])->pluck('value')->all();
    expect(array_slice($values, 0, 2))->toBe(['Blue', 'Red']);
});

test('the homepage brands toggle (bulk update) shows up immediately', function () {
    $brand = Brand::factory()->create();
    $brandsSection = HomepageSetting::updateOrCreate(['section_key' => 'brands'], ['section_name' => 'Brands']);
    filterProps();

    actingAs(catalogAdmin(), 'admin')
        ->put(route('admin.homepage.update', $brandsSection), ['title' => null, 'description' => null, 'eyebrow' => null, 'action_label' => null, 'action_href' => null, 'is_visible' => true, 'section_brands' => []])
        ->assertSessionHasNoErrors();
    auth('admin')->logout();

    expect(collect(filterProps()['brands'])->firstWhere('id', $brand->id))->toBeNull();
});

test('filter options survive a real persistent cache store between requests', function () {
    $directory = sys_get_temp_dir().'/catalog-filter-cache-test-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory]);
    Cache::purge('file');

    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);
    SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true]);
    listedProduct(['category_id' => $category->id, 'price' => 750]);

    try {
        $first = filterProps();
        expect(filterProps())->toBe($first)
            ->and($first['maxPrice'])->toBe(750)
            ->and(collect($first['categories'])->firstWhere('id', $category->id)['sub_categories'])->toHaveCount(1);
    } finally {
        File::deleteDirectory($directory);
    }
});
