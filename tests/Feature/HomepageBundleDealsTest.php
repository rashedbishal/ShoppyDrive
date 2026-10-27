<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
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
