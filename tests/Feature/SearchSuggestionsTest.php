<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Support\ProductSearchQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SEARCH-AUTOCOMPLETE-FT-001. InnoDB's FULLTEXT index only sees committed
 * rows, so the MySQL cases leave RefreshDatabase's wrapping transaction and
 * commit real rows. That only ever happens on a shoppydrive_test_ database:
 * Tests\SafeTestDatabase refuses to run on anything else.
 */
function commitToFullTextIndex(): void
{
    DB::purge('mysql');

    expect(DB::connection()->getDatabaseName())->toStartWith('shoppydrive_test_');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function suggestedProduct(array $attributes): Product
{
    return Product::factory()->create(array_merge([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'short_description' => null,
    ], $attributes));
}

/**
 * @param  array<int, string>  $names
 */
function expectSuggestions(string $term, array $names): void
{
    $response = test()->getJson('/api/search/suggestions?q='.urlencode($term));

    $response->assertOk();

    expect(collect($response->json('products'))->pluck('name')->sort()->values()->all())->toBe(collect($names)->sort()->values()->all());
}

/**
 * @param  Collection<int, Product>  $products
 */
function removeSuggestedProducts(Collection $products): void
{
    $products->each(fn (Product $product) => $product->forceDelete());
}

describe('on MySQL with the real FULLTEXT index', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('requires real MySQL with its FULLTEXT index');
        }

        commitToFullTextIndex();
    });

    test('suggestions work and match the name by word prefix', function () {
        $products = collect([suggestedProduct(['name' => 'Stainless Steel Flask', 'sku' => 'FT-1001'])]);

        try {
            expectSuggestions('flask', ['Stainless Steel Flask']);
            expectSuggestions('fla', ['Stainless Steel Flask']);
            expectSuggestions('steel stainless', ['Stainless Steel Flask']);
            expectSuggestions('nomatchword', []);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('a word found only in the description is not suggested', function () {
        $products = collect([
            suggestedProduct(['name' => 'Copper Lantern', 'short_description' => 'Pairs well with a zanzibar kettle']),
            suggestedProduct(['name' => 'Zanzibar Kettle']),
        ]);

        try {
            expectSuggestions('zanzibar', ['Zanzibar Kettle']);
            expectSuggestions('copper kettle', []);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('the SKU still matches, alone or with a term the index rejects', function () {
        $products = collect([suggestedProduct(['name' => 'Walnut Tray', 'sku' => 'WLN-7788'])]);

        try {
            expectSuggestions('WLN-77', ['Walnut Tray']);
            expectSuggestions('7788', ['Walnut Tray']);
            expectSuggestions('wa', ['Walnut Tray']);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('hyphenated terms and boolean operators are safe', function () {
        $products = collect([suggestedProduct(['name' => 'Classic T-Shirt'])]);

        try {
            expectSuggestions('T-Shirt', ['Classic T-Shirt']);
            expectSuggestions('+shirt*', ['Classic T-Shirt']);
            expectSuggestions('(shirt)', ['Classic T-Shirt']);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('inactive and unpublished products stay hidden', function () {
        $products = collect([
            suggestedProduct(['name' => 'Harbour Mug Visible']),
            suggestedProduct(['name' => 'Harbour Mug Inactive', 'is_active' => false]),
            suggestedProduct(['name' => 'Harbour Mug Unpublished', 'published_at' => null]),
        ]);

        try {
            expectSuggestions('harbour', ['Harbour Mug Visible']);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('featured products come first and the limit still applies', function () {
        $products = collect(range(1, 5))->map(fn (int $number) => suggestedProduct(['name' => "Meadow Candle {$number}", 'is_featured' => false]));
        $featured = suggestedProduct(['name' => 'Meadow Candle Featured', 'is_featured' => true]);
        $products->push($featured);

        try {
            $names = collect($this->getJson('/api/search/suggestions?q=meadow')->assertOk()->json('products'))->pluck('name');

            expect($names)->toHaveCount(4);
            expect($names->first())->toBe('Meadow Candle Featured');
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('brands and categories come back in the same response', function () {
        $brand = Brand::factory()->create(['name' => 'Orchard Brand', 'is_active' => true]);
        $category = Category::factory()->create(['name' => 'Orchard Category', 'is_active' => true]);
        $products = collect([suggestedProduct(['name' => 'Orchard Basket'])]);

        try {
            $response = $this->getJson('/api/search/suggestions?q=orchard')->assertOk();

            expect(collect($response->json('products'))->pluck('name')->all())->toBe(['Orchard Basket']);
            expect(collect($response->json('brands'))->pluck('name')->all())->toBe(['Orchard Brand']);
            expect(collect($response->json('categories'))->pluck('name')->all())->toBe(['Orchard Category']);
        } finally {
            removeSuggestedProducts($products);
            $brand->delete();
            $category->delete();
        }
    });

    test('the main search still matches descriptions', function () {
        $products = collect([suggestedProduct(['name' => 'Copper Lantern', 'short_description' => 'Pairs well with a zanzibar kettle'])]);

        try {
            $found = data_get($this->get(route('search', ['q' => 'zanzibar']))->assertOk()->viewData('page')['props'], 'products.data');

            expect(collect($found)->pluck('name')->all())->toBe(['Copper Lantern']);
        } finally {
            removeSuggestedProducts($products);
        }
    });

    test('name-only searches use the FULLTEXT index, and the main search query is unchanged', function () {
        $nameOnly = Product::query();
        ProductSearchQuery::apply($nameOnly, 'flask', includeShortDescription: false);

        $main = Product::query();
        ProductSearchQuery::apply($main, 'flask');

        expect($nameOnly->toSql())->toContain('match (`name`, `short_description`) against')->toContain('`name` like');
        expect($main->toSql())->toContain('match (`name`, `short_description`) against')->not->toContain('`name` like');
    });
});

test('a product on an active flash sale suggests its sale price, not the regular one', function () {
    $product = suggestedProduct(['name' => 'Comfy T-Shirt', 'price' => 2000]);
    $flashSale = FlashSale::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 1500,
    ]);

    $response = test()->getJson('/api/search/suggestions?q=comfy');

    $response->assertOk();
    $suggested = collect($response->json('products'))->firstWhere('id', $product->id);
    expect($suggested)->not->toBeNull();
    expect($suggested['in_flash_sale'])->toBeTrue();
    expect($suggested['flash_sale_price'])->toEqual(1500);
});

test('a product with no active flash sale suggests its regular price', function () {
    $product = suggestedProduct(['name' => 'Plain Mug', 'price' => 500]);

    $response = test()->getJson('/api/search/suggestions?q=plain');

    $response->assertOk();
    $suggested = collect($response->json('products'))->firstWhere('id', $product->id);
    expect($suggested)->not->toBeNull();
    expect($suggested['in_flash_sale'])->toBeFalse();
    expect($suggested['flash_sale_price'])->toBeNull();
    expect($suggested['price'])->toBe(500);
});

describe('on SQLite', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('covers the SQLite fallback');
        }
    });

    test('suggestions still use the name and SKU, never the description', function () {
        suggestedProduct(['name' => 'Copper Lantern', 'short_description' => 'Pairs well with a zanzibar kettle', 'sku' => 'CPR-1']);
        suggestedProduct(['name' => 'Zanzibar Kettle', 'sku' => 'ZNZ-1']);

        expectSuggestions('zanzibar', ['Zanzibar Kettle']);
        expectSuggestions('CPR-1', ['Copper Lantern']);
    });

    test('suggestions still match mid-word through the LIKE fallback', function () {
        suggestedProduct(['name' => 'Stainless Steel Flask']);

        expectSuggestions('lask', ['Stainless Steel Flask']);
    });

    test('the query takes no FULLTEXT path', function () {
        $query = Product::query();
        ProductSearchQuery::apply($query, 'flask', includeShortDescription: false);

        expect(strtolower($query->toSql()))->not->toContain('match');
    });
});
