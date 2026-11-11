<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * ProductDetail.tsx shows the brand label, as a link to the brand page,
 * only when `product.brand` is set. Previously a product with no brand
 * showed a "GENUINE PRODUCT" link to a 404. The link needs no is_active
 * check because a product whose brand is inactive never renders at all.
 */
test('a product without a brand sends no brand, so no label is shown', function (): void {
    $product = Product::factory()->create(['brand_id' => null]);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('product.brand', null));
});

test('a branded product sends its brand, whose page loads', function (): void {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.brand.name', $brand->name)
            ->where('product.brand.slug', $brand->slug));

    $this->get(route('brand.show', $brand->slug))->assertOk();
});

test('a product whose brand is inactive 404s, so its brand link never renders', function (): void {
    $brand = Brand::factory()->create(['is_active' => false]);
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $this->get(route('product.show', $product->slug))->assertNotFound();
});
