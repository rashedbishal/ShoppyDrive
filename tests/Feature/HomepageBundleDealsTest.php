<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an active bundle with its components appears in the homepage bundle_deals prop', function () {
    $a = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $b = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FixedPrice, 'bundle_price' => 100, 'sort_order' => 0]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->has('bundleDeals', 1)
        ->where('bundleDeals.0.id', $bundle->id)
        ->has('bundleDeals.0.items', 2)
    );
});

test('an inactive bundle does not appear on the homepage', function () {
    $a = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $b = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $bundle = ProductBundle::factory()->create(['is_active' => false]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id]);

    $this->get('/')->assertInertia(fn ($page) => $page->has('bundleDeals', 0));
});

test('a bundle missing a component because its product was deactivated is excluded', function () {
    $a = Product::factory()->create(['is_active' => false, 'published_at' => now()->subDay()]);
    $b = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $bundle = ProductBundle::factory()->create();
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id]);

    $this->get('/')->assertInertia(fn ($page) => $page->has('bundleDeals', 0));
});

test('the homepage loads fine with no bundles at all', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->has('bundleDeals', 0));
});

// ── How many complete sets current stock can fill ───────────────────────

dataset('bundle storefront pages', [
    'home page' => ['/', 'bundleDeals'],
    'bundle deals page' => ['/bundle-deals', 'bundles'],
]);

function bundleWithStockedComponents(array $firstItem, array $secondItem): ProductBundle
{
    $bundle = ProductBundle::factory()->create();

    foreach ([$firstItem, $secondItem] as $item) {
        ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, ...$item]);
    }

    return $bundle;
}

function visibleProductWithStock(int $stock): Product
{
    return Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'stock_quantity' => $stock]);
}

test('a bundle reports how many complete sets its scarcest component can fill', function (string $url, string $prop) {
    bundleWithStockedComponents(
        ['product_id' => visibleProductWithStock(7)->id, 'quantity' => 2],
        ['product_id' => visibleProductWithStock(10)->id, 'quantity' => 5],
    );

    $this->get($url)->assertInertia(fn ($page) => $page->where("{$prop}.0.available_sets", 2));
})->with('bundle storefront pages');

test('a bundle sold down below its recipe is out of stock, and returns once refilled', function (string $url, string $prop) {
    $scarce = visibleProductWithStock(2);
    bundleWithStockedComponents(
        ['product_id' => $scarce->id, 'quantity' => 3],
        ['product_id' => visibleProductWithStock(10)->id, 'quantity' => 1],
    );

    $this->get($url)->assertInertia(fn ($page) => $page->has($prop, 1)->where("{$prop}.0.available_sets", 0));

    $scarce->update(['stock_quantity' => 3]);

    $this->get($url)->assertInertia(fn ($page) => $page->where("{$prop}.0.available_sets", 1));
})->with('bundle storefront pages');

test('a chosen variant is limited by its own stock, not the product total', function (string $url, string $prop) {
    $product = visibleProductWithStock(50);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 1]);
    bundleWithStockedComponents(
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2],
        ['product_id' => visibleProductWithStock(10)->id, 'quantity' => 1],
    );

    $this->get($url)->assertInertia(fn ($page) => $page->where("{$prop}.0.available_sets", 0));
})->with('bundle storefront pages');

test('a component with no variant chosen on a product that has variants cannot be sold', function (string $url, string $prop) {
    $product = visibleProductWithStock(50);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 20]);
    bundleWithStockedComponents(
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => visibleProductWithStock(10)->id, 'quantity' => 1],
    );

    $this->get($url)->assertInertia(fn ($page) => $page->where("{$prop}.0.available_sets", 0));
})->with('bundle storefront pages');
