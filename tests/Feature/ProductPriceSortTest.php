<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Item 28: "Price: Low to High" / "High to Low" must order products by the
 * price their card shows — the flash-sale price while one is running.
 */

function sortableProduct(string $name, float $price): Product
{
    return Product::factory()->create([
        'name' => $name,
        'price' => $price,
        'published_at' => now()->subDay(),
    ]);
}

function putOnFlashSale(Product $product, float $salePrice, array $item = [], array $sale = []): void
{
    FlashSaleItem::factory()->create([
        'flash_sale_id' => FlashSale::factory()->create($sale + [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ])->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => $salePrice,
    ] + $item);
}

/** @return list<string> */
function sortedNames(string $routeName, array $query): array
{
    $names = [];

    test()->get(route($routeName, $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names): void {
            $names = array_column($page->toArray()['props']['products']['data'], 'name');
        });

    return $names;
}

it('sorts a product on flash sale by its sale price on the listing page', function (string $sort, array $expected): void {
    sortableProduct('Mid', 800);
    sortableProduct('High', 1500);
    putOnFlashSale(sortableProduct('Discounted', 2000), 500);

    expect(sortedNames('products.index', ['sort' => $sort]))->toBe($expected);
})->with([
    'low to high' => ['price_asc', ['Discounted', 'Mid', 'High']],
    'high to low' => ['price_desc', ['High', 'Mid', 'Discounted']],
]);

it('sorts a product on flash sale by its sale price in search results', function (string $sort, array $expected): void {
    sortableProduct('Lamp Mid', 800);
    sortableProduct('Lamp High', 1500);
    putOnFlashSale(sortableProduct('Lamp Discounted', 2000), 500);

    expect(sortedNames('search', ['q' => 'Lamp', 'sort' => $sort]))->toBe($expected);
})->with([
    'low to high' => ['price_asc', ['Lamp Discounted', 'Lamp Mid', 'Lamp High']],
    'high to low' => ['price_desc', ['Lamp High', 'Lamp Mid', 'Lamp Discounted']],
]);

it('sorts by the regular price when the flash sale does not apply right now', function (array $item, array $sale): void {
    sortableProduct('Mid', 800);
    sortableProduct('High', 1500);
    putOnFlashSale(sortableProduct('Top', 2000), 500, $item, $sale);

    expect(sortedNames('products.index', ['sort' => 'price_asc']))->toBe(['Mid', 'High', 'Top']);
})->with([
    'campaign switched off' => [[], ['is_active' => false]],
    'campaign ended' => [[], ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]],
    'campaign not started' => [[], ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]],
    'sale stock sold out' => [['stock_limit' => 5, 'sold_count' => 5], []],
]);

it('uses the cheapest sale price when a product is in two running campaigns', function (): void {
    sortableProduct('Mid', 800);
    $product = sortableProduct('Twice', 2000);
    putOnFlashSale($product, 1200);
    putOnFlashSale($product, 500);

    expect(sortedNames('products.index', ['sort' => 'price_asc']))->toBe(['Twice', 'Mid']);
});
