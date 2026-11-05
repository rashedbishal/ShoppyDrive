<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the product listing page', function (): void {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductListing')
            ->where('title', 'All Products')
            ->where('category', null)
            ->where('listingUrl', route('products.index'))
        );
});

it('reports price and rating filters as unset when absent from the query', function (): void {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.min_price', null)
            ->where('filters.max_price', null)
            ->where('filters.rating', null)
        );
});

it('renders a category listing page', function (): void {
    $category = Category::query()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->get(route('category.show', $category->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductListing')
            ->where('title', 'Electronics')
            ->where('category.id', $category->id)
            ->where('listingUrl', route('category.show', $category->slug))
        );
});

// Regression: the listing page's "Add to Cart" quick-add resolves the
// product's default variant client-side (ProductCard) and needs `variants`
// on hand to do it — without this eager load an order placed from a card
// quick-add on a variant product is wrongly rejected as out of stock at
// checkout (InventoryService::decrementForOrderItem treats a variant-less
// line on a product that has variants as invalid).
it('includes each product\'s variants, with the default flagged, on the listing page', function (): void {
    $product = Product::factory()->create(['published_at' => now()->subDay()]);

    $colorAttribute = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $red = AttributeValue::create(['attribute_id' => $colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);

    ProductVariant::create([
        'product_id' => $product->id,
        'color_value_id' => $red->id,
        'price' => $product->price,
        'stock_quantity' => 5,
        'attributes' => ['Color' => 'Red'],
        'is_default' => true,
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.variants.0.is_default', true));
});

it('shows a product on an active flash sale at its sale price in the listing grid', function (): void {
    $product = Product::factory()->create(['price' => 2000, 'published_at' => now()->subDay()]);
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

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.in_flash_sale', true)
            ->where('products.data.0.flash_sale_price', 1500)
            ->where('products.data.0.price', 2000));
});

it('shows a product with no active flash sale at its regular price in the listing grid', function (): void {
    Product::factory()->create(['price' => 800, 'published_at' => now()->subDay()]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.in_flash_sale', false)
            ->where('products.data.0.flash_sale_price', null));
});

/*
 * PERF-001: ProductVariant::$appends = ['image'] reads its own `media`
 * relation on serialization — without eager-loading variants.media, that's
 * one extra query per variant across the whole page (12 products x 6
 * variants measured 101 queries in the audit).
 */
it('does not run one query per variant on the product listing', function (): void {
    Product::factory()->count(12)->create(['is_active' => true, 'published_at' => now()->subDay()])
        ->each(fn (Product $product) => ProductVariant::factory()->count(6)->create(['product_id' => $product->id]));

    DB::enableQueryLog();
    $this->get(route('products.index'))->assertOk();
    $count = count(DB::getQueryLog());

    expect($count)->toBeLessThan(40);
});
