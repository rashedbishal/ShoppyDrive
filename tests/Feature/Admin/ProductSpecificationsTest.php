<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;

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
function specProductPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Spec Product',
        'slug' => 'spec-product',
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'price' => 500,
        'stock_quantity' => 10,
        'is_active' => true,
    ], $overrides);
}

test('creating a product with specifications persists them in order', function () {
    $specifications = [
        ['key' => 'SKU', 'value' => 'BS4703'],
        ['key' => 'Brand', 'value' => 'Languo'],
        ['key' => 'Unit', 'value' => 'Pcs'],
    ];

    $this->post(route('admin.products.store'), specProductPayload(['specifications' => $specifications]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'spec-product')->firstOrFail();

    expect($product->specifications)->toBe($specifications);
});

test('a product with no specifications stores null', function () {
    $this->post(route('admin.products.store'), specProductPayload())
        ->assertSessionHasNoErrors();

    $product = Product::where('slug', 'spec-product')->firstOrFail();

    expect($product->specifications)->toBeNull();
});

test('a specification row needs both a key and a value', function () {
    $this->post(route('admin.products.store'), specProductPayload([
        'specifications' => [['key' => 'SKU', 'value' => '']],
    ]))->assertSessionHasErrors('specifications.0.value');

    expect(Product::where('slug', 'spec-product')->exists())->toBeFalse();
});

test('updating a product replaces its specifications', function () {
    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'specifications' => [['key' => 'Old Key', 'value' => 'Old Value']],
    ]);

    $this->put(route('admin.products.update', $product), specProductPayload([
        'slug' => $product->slug,
        'stock_token' => ProductStockToken::for($product),
        'specifications' => [['key' => 'New Key', 'value' => 'New Value']],
    ]))->assertSessionHasNoErrors();

    expect($product->refresh()->specifications)->toBe([['key' => 'New Key', 'value' => 'New Value']]);
});

test('the storefront product page exposes specifications to the page props', function () {
    $product = Product::factory()->create([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'is_active' => true,
        'published_at' => now()->subDay(),
        'specifications' => [['key' => 'SKU', 'value' => 'BS4703']],
    ]);

    auth('admin')->logout();

    $this->get(route('product.show', $product))
        ->assertInertia(fn ($page) => $page
            ->component('catalog/ProductDetail')
            ->where('product.specifications', [['key' => 'SKU', 'value' => 'BS4703']])
        );
});
test('the admin create-product page renders successfully', function () {
    $this->get(route('admin.products.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/products/create'));
});
