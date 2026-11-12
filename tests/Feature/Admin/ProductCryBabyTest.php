<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

/**
 * @return array<string, mixed>
 */
function cryBabyProductPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => $product->name,
        'slug' => $product->slug,
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => $product->category_id,
        'sub_category_id' => $product->sub_category_id,
        'price' => $product->price,
        'stock_quantity' => $product->stock_quantity,
        'is_active' => true,
    ], $overrides);
}

test('the cry baby easter egg is off by default for new products', function () {
    $product = Product::factory()->create();

    expect($product->fresh()->cry_baby_enabled)->toBeFalse();
});

test('admin can turn the cry baby easter egg on for all products', function () {
    $products = Product::factory()->count(3)->create();

    actingAs($this->admin, 'admin')
        ->from(route('admin.products.index'))
        ->patch(route('admin.products.cry-baby.update-all'), ['enabled' => true])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    expect(Product::where('cry_baby_enabled', true)->count())->toBe($products->count());
});

test('admin can turn the cry baby easter egg off for all products', function () {
    Product::factory()->count(3)->create(['cry_baby_enabled' => true]);

    actingAs($this->admin, 'admin')
        ->from(route('admin.products.index'))
        ->patch(route('admin.products.cry-baby.update-all'), ['enabled' => false])
        ->assertRedirect(route('admin.products.index'));

    expect(Product::where('cry_baby_enabled', true)->count())->toBe(0);
});

test('bulk cry baby toggle requires a boolean enabled flag', function () {
    actingAs($this->admin, 'admin')
        ->patch(route('admin.products.cry-baby.update-all'), [])
        ->assertInvalid(['enabled']);
});

test('guests cannot bulk toggle the cry baby easter egg', function () {
    Product::factory()->create();

    $this->patch(route('admin.products.cry-baby.update-all'), ['enabled' => true]);

    expect(Product::where('cry_baby_enabled', true)->count())->toBe(0);
});

test('admin can enable the cry baby easter egg for an individual product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + cryBabyProductPayload($product, ['cry_baby_enabled' => true]))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->cry_baby_enabled)->toBeTrue()
        ->and($otherProduct->fresh()->cry_baby_enabled)->toBeFalse();
});

test('unchecking the cry baby easter egg on an individual product turns it off', function () {
    $product = Product::factory()->create(['cry_baby_enabled' => true]);

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + cryBabyProductPayload($product))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->cry_baby_enabled)->toBeFalse();
});

test('admin can create a product with the cry baby easter egg enabled', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create();

    actingAs($this->admin, 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Crying Toy',
            'slug' => 'crying-toy',
            'short_description' => 'A short description.',
            'description' => '<p>A full description.</p>',
            'images' => [UploadedFile::fake()->image('product.jpg', 400, 400)],
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'price' => 500,
            'stock_quantity' => 10,
            'is_active' => true,
            'cry_baby_enabled' => true,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect(Product::where('slug', 'crying-toy')->first()->cry_baby_enabled)->toBeTrue();
});

test('admin products index exposes the cry baby enabled count', function () {
    Product::factory()->count(2)->create(['cry_baby_enabled' => true]);
    Product::factory()->create();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/products/index')
            ->where('cryBabyEnabledCount', 2));
});

test('the storefront product page receives the cry baby flag', function () {
    $product = Product::factory()->create([
        'cry_baby_enabled' => true,
        'published_at' => now(),
    ]);

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductDetail')
            ->where('product.cry_baby_enabled', true));
});
