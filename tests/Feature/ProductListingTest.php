<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
