<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(createAdmin(), 'admin');

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function keywordProductPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'শার্ট',
        'slug' => 'bangla-shirt',
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'images' => [UploadedFile::fake()->image('product.jpg', 400, 400)],
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'price' => 500,
        'stock_quantity' => 10,
        'is_active' => true,
    ], $overrides);
}

test('creating a product persists its admin-entered search keywords', function () {
    $this->post(route('admin.products.store'), keywordProductPayload(['meta_keywords' => 'shirt, t-shirt']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'bangla-shirt')->firstOrFail();

    expect($product->meta_keywords)->toBe('shirt, t-shirt');
});

test('a product with no search keywords stores null', function () {
    $this->post(route('admin.products.store'), keywordProductPayload())
        ->assertSessionHasNoErrors();

    $product = Product::where('slug', 'bangla-shirt')->firstOrFail();

    expect($product->meta_keywords)->toBeNull();
});

test('updating a product replaces its search keywords', function () {
    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'meta_keywords' => 'old keyword',
    ]);

    $this->put(route('admin.products.update', $product), keywordProductPayload([
        'slug' => $product->slug,
        'stock_token' => ProductStockToken::for($product),
        'meta_keywords' => 'shirt, formal shirt',
    ]))->assertSessionHasNoErrors();

    expect($product->refresh()->meta_keywords)->toBe('shirt, formal shirt');
});
