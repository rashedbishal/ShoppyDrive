<?php

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function createActiveFlashSale(): FlashSale
{
    return FlashSale::create([
        'name' => 'Weekend Flash Sale',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
}

function addFlashSaleItem(FlashSale $flashSale, Product $product): FlashSaleItem
{
    return FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'sale_price' => 50,
        'stock_limit' => 10,
    ]);
}

test('flash sales page excludes items whose product was soft-deleted', function () {
    $live = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $deleted = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $flashSale = createActiveFlashSale();
    addFlashSaleItem($flashSale, $live);
    addFlashSaleItem($flashSale, $deleted);
    $deleted->delete();

    $response = get(route('flash-sales.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('catalog/FlashSales')
        ->has('activeSale.items', 1)
        ->where('activeSale.items.0.product.id', $live->id)
    );
});

test('flash sales page excludes items whose product was deactivated', function () {
    $live = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $inactive = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $flashSale = createActiveFlashSale();
    addFlashSaleItem($flashSale, $live);
    addFlashSaleItem($flashSale, $inactive);
    $inactive->update(['is_active' => false]);

    $response = get(route('flash-sales.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('catalog/FlashSales')
        ->has('activeSale.items', 1)
        ->where('activeSale.items.0.product.id', $live->id)
    );
});

test('homepage excludes flash sale items whose product was soft-deleted', function () {
    $live = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $deleted = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $flashSale = createActiveFlashSale();
    addFlashSaleItem($flashSale, $live);
    addFlashSaleItem($flashSale, $deleted);
    $deleted->delete();

    $response = get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->has('activeFlashSale.items', 1)
        ->where('activeFlashSale.items.0.product.id', $live->id)
    );
});
