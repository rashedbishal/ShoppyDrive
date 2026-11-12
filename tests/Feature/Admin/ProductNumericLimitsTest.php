<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
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
function numericLimitPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Limit Product',
        'slug' => 'limit-product',
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

test('an oversized price field is a validation error, not a database exception', function (string $field, mixed $value) {
    $this->post(route('admin.products.store'), numericLimitPayload([$field => $value]))
        ->assertSessionHasErrors($field);

    expect(Product::count())->toBe(0);
})->with([
    'price' => ['price', '123456789012345'],
    'price just over the column limit' => ['price', '100000000'],
    'compare_price' => ['compare_price', '123456789012345'],
    'cost_price' => ['cost_price', '123456789012345'],
    'stock_quantity' => ['stock_quantity', '99999999999'],
    'low_stock_threshold' => ['low_stock_threshold', '99999999999'],
]);

test('an oversized variant price is a validation error', function () {
    $this->post(route('admin.products.store'), numericLimitPayload([
        'price' => null,
        'stock_quantity' => null,
        'variants' => [['price' => '123456789012345', 'stock_quantity' => 5]],
    ]))->assertSessionHasErrors('variants.0.price');

    expect(Product::count())->toBe(0);
});

test('the largest value the price column holds is still accepted', function () {
    $this->post(route('admin.products.store'), numericLimitPayload(['price' => '99999999.99']))
        ->assertSessionHasNoErrors();

    expect((float) Product::firstOrFail()->price)->toBe(99999999.99);
});

test('a new product needs a short description, a description and an image', function (string $field, mixed $value) {
    $payload = numericLimitPayload();
    unset($payload[$field]);

    $this->post(route('admin.products.store'), $payload + [$field => $value])
        ->assertSessionHasErrors($field);

    expect(Product::count())->toBe(0);
})->with([
    'short description missing' => ['short_description', null],
    'short description blank' => ['short_description', '   '],
    'description missing' => ['description', null],
    'description is only empty markup' => ['description', '<p><br></p>&nbsp;'],
    'image missing' => ['images', null],
    'image list empty' => ['images', []],
]);

test('the messages name the field they are about', function () {
    $this->post(route('admin.products.store'), ['name' => 'X'])
        ->assertSessionHasErrors([
            'short_description' => 'The short description field is required.',
            'description' => 'The description field is required.',
            'images' => 'Add at least one product image.',
        ]);
});
