<?php

use App\Models\Brand;
use App\Models\HomepageSetting;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage brand section reports the number of visible products per brand', function () {
    $brand = Brand::factory()->create();
    $emptyBrand = Brand::factory()->create();

    Product::factory()->count(2)->create(['brand_id' => $brand->id, 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->create(['brand_id' => $brand->id, 'is_active' => false]);

    HomepageSetting::create([
        'section_key' => 'brands', 'section_name' => 'Brands',
        'is_visible' => true, 'sort_order' => 0,
        'metadata' => ['brand_ids' => [$brand->id, $emptyBrand->id]],
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('brands', fn ($brands) => collect($brands)->pluck('products_count', 'id')->all() == [
            $brand->id => 2,
            $emptyBrand->id => 0,
        ])
    );
});
