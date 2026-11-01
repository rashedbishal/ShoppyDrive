<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
});

test('creating a product strips malicious HTML from description', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), [
        'name' => 'Test Product',
        'slug' => 'test-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => '1000',
        'stock_quantity' => '10',
        'is_active' => true,
        'description' => '<p>Great product</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
    ]);

    $response->assertRedirect();

    $product = Product::where('slug', 'test-product')->first();

    expect($product->description)->not->toContain('<script');
    expect($product->description)->not->toContain('onerror');
    expect($product->description)->toContain('Great product');
});

test('updating a product preserves legitimate formatting in description', function () {
    actingAs($this->admin, 'admin');

    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
    ]);

    $response = $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + [
        'name' => $product->name,
        'slug' => $product->slug,
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => '1000',
        'stock_quantity' => '10',
        'is_active' => true,
        'description' => '<p>Check out our <b>new</b> <a href="https://example.com">collection</a>.</p>',
    ]);

    $response->assertRedirect();

    expect($product->refresh()->description)
        ->toContain('<b>new</b>')
        ->toContain('href="https://example.com"');
});

test('a relative uploaded image URL in description survives sanitization', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), [
        'name' => 'Test Product',
        'slug' => 'test-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => '1000',
        'stock_quantity' => '10',
        'is_active' => true,
        'description' => '<p>See the image below.</p><img src="/storage/content-images/sample.webp" alt="sample">',
    ]);

    $response->assertRedirect();

    expect(Product::where('slug', 'test-product')->first()->description)
        ->toContain('/storage/content-images/sample.webp');
});
