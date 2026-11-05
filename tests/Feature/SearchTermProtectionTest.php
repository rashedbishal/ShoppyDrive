<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\SubCategory;
use App\Support\SearchTerm;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function searchProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(['is_active' => true, 'published_at' => now()->subDay()], $attributes));
}

/**
 * Only queries that look up products by what was searched.
 */
function productQueriesDuring(Closure $request): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $request();
    $count = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'from "products"'))->count();
    DB::disableQueryLog();

    return $count;
}

test('junk gets empty suggestions without touching products', function (string $junk) {
    searchProduct(['name' => 'Cotton Shirt']);

    expect(productQueriesDuring(function () use ($junk) {
        $this->getJson('/api/search/suggestions?q='.urlencode($junk))
            ->assertOk()
            ->assertExactJson(['products' => [], 'brands' => [], 'categories' => [], 'subcategories' => []]);
    }))->toBe(0);
})->with([
    'spaces' => ['      '],
    'symbols' => ['!!!!!!'],
    'mixed symbols' => ['@#$%^&*'],
    'percent wildcards' => ['%%%%'],
    'emoji' => ['😀😀😀'],
    'one letter' => ['a'],
    'one letter among symbols' => ['-a-'],
    'a non-breaking space' => ["a\u{00A0}"],
]);

test('malformed input is treated as no search rather than an error', function (string $query) {
    $this->getJson('/api/search/suggestions?'.$query)
        ->assertOk()
        ->assertExactJson(['products' => [], 'brands' => [], 'categories' => [], 'subcategories' => []]);

    $this->get('/search?'.$query)->assertOk();
    $this->get('/products?'.$query)->assertOk();
})->with([
    'an array' => ['q[]=shirt'],
    'invalid utf-8' => ['q=%FF%FE'],
]);

test('real searches still find products', function (string $name, string $term) {
    searchProduct(['name' => $name]);

    $this->getJson('/api/search/suggestions?q='.urlencode($term))
        ->assertOk()
        ->assertJsonPath('products.0.name', $name);
})->with([
    'two letters' => ['Xy Case', 'xy'],
    'Bangla word' => ['ফোন কভার', 'ফোন'],
    'Bangla with a vowel sign' => ['কি বোর্ড', 'কি'],
    'a number' => ['Phone 15', '15'],
    'a code with symbols' => ['Shirt WM-44', 'WM-44'],
    'extra spaces inside' => ['Red Shirt', 'red    shirt'],
    'tabs and line breaks' => ['Red Shirt', "red\t\nshirt"],
]);

test('a very long search is cut to the limit', function () {
    expect(mb_strlen(SearchTerm::normalize(str_repeat('ab ', 100))))->toBeLessThanOrEqual(SearchTerm::MAX_LENGTH)
        ->and(SearchTerm::normalize(str_repeat('ফ', 100)))->toBe(str_repeat('ফ', SearchTerm::MAX_LENGTH));
});

test('search results show nothing for junk, without searching products', function (string $url) {
    searchProduct(['name' => 'Cotton Shirt']);

    $this->get($url.'?q=%21%21%21%21')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('products.total', 0));
})->with(['search page' => ['/search'], 'products page' => ['/products']]);

test('a blank search still lists all products, so filtering keeps working', function (string $url) {
    searchProduct(['name' => 'Cotton Shirt']);

    foreach (['', '?q=', '?q=%20%20%20'] as $query) {
        $this->get($url.$query)->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
    }
})->with(['search page' => ['/search'], 'products page' => ['/products']]);

test('the search page finds products and keeps the visitor\'s capitals and cleaned text', function () {
    searchProduct(['name' => 'Red Shirt']);

    $this->get('/search?q='.urlencode('  Red    Shirt '))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('query', 'Red Shirt')
        ->where('filters.q', 'Red Shirt')
        ->where('products.total', 1));
});

test('suggestions still include brands, categories and subcategories', function () {
    $sub = SubCategory::factory()->create(['name' => 'Meadow Wear']);

    $this->getJson('/api/search/suggestions?q=meadow')->assertOk()->assertJsonPath('subcategories.0.id', $sub->id);
});
