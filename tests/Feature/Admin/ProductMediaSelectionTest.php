<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    actingAs(createAdmin(), 'admin');

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mediaProductPayload(array $overrides = []): array
{
    return array_merge([
        // Owner decision 2026-11-14: starting stock and stock changes need the admin's password.
        'stock_password' => 'password',
        'name' => 'Media Product',
        'slug' => 'media-product',
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'price' => 500,
        'stock_quantity' => 10,
        'is_active' => true,
        'images' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
        ],
    ], $overrides);
}

/**
 * @return array{primary: int|null, hover: list<int>, order: list<int>}
 */
function savedMediaFlags(): array
{
    $media = Product::where('slug', 'media-product')->firstOrFail()->media()->orderBy('sort_order')->get()->values();

    return [
        'primary' => $media->search(fn (ProductMedia $item) => $item->is_primary) === false
            ? null
            : $media->search(fn (ProductMedia $item) => $item->is_primary),
        'hover' => $media->filter(fn (ProductMedia $item) => $item->is_hover)->keys()->all(),
        'order' => $media->pluck('sort_order')->all(),
    ];
}

test('the chosen default and hover pictures are saved without reordering the gallery', function () {
    $this->post(route('admin.products.store'), mediaProductPayload([
        'primary_image_index' => 2,
        'hover_image_index' => 0,
    ]))->assertSessionHasNoErrors();

    expect(savedMediaFlags())->toBe(['primary' => 2, 'hover' => [0], 'order' => [0, 1, 2]]);
});

test('picture indexes submitted as form text are saved', function () {
    $this->post(route('admin.products.store'), mediaProductPayload([
        'primary_image_index' => '2',
        'hover_image_index' => '0',
    ]))->assertSessionHasNoErrors();

    expect(savedMediaFlags())->toMatchArray(['primary' => 2, 'hover' => [0]]);
});

test('with nothing chosen the first picture is the default and there is no hover picture', function () {
    $this->post(route('admin.products.store'), mediaProductPayload())->assertSessionHasNoErrors();

    expect(savedMediaFlags())->toMatchArray(['primary' => 0, 'hover' => []]);
});

test('a default index that points at nothing falls back to the first picture', function () {
    $this->post(route('admin.products.store'), mediaProductPayload(['primary_image_index' => 9]))
        ->assertSessionHasNoErrors();

    expect(savedMediaFlags()['primary'])->toBe(0);
});

test('the default picture cannot also be the hover picture', function () {
    $this->post(route('admin.products.store'), mediaProductPayload([
        'primary_image_index' => 1,
        'hover_image_index' => 1,
    ]))->assertSessionHasNoErrors();

    expect(savedMediaFlags())->toMatchArray(['primary' => 1, 'hover' => []]);
});

test('a video cannot be the hover picture', function () {
    $this->post(route('admin.products.store'), mediaProductPayload([
        'images' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
        ],
        'hover_image_index' => 1,
    ]))->assertSessionHasNoErrors();

    expect(savedMediaFlags()['hover'])->toBe([]);
});

test('invalid picture indexes are rejected', function (string $field, mixed $value) {
    $this->post(route('admin.products.store'), mediaProductPayload([$field => $value]))
        ->assertSessionHasErrors($field);

    expect(Product::where('slug', 'media-product')->exists())->toBeFalse();
})->with([
    ['primary_image_index', -1],
    ['primary_image_index', 'first'],
    ['hover_image_index', -1],
    ['hover_image_index', 'first'],
]);

test('updating a product can change and clear its default and hover pictures', function () {
    $this->post(route('admin.products.store'), mediaProductPayload(['hover_image_index' => 1]))
        ->assertSessionHasNoErrors();

    $product = Product::where('slug', 'media-product')->firstOrFail();
    $paths = $product->media()->orderBy('sort_order')->pluck('path')->all();

    $update = fn (array $picks) => $this->put(route('admin.products.update', $product), mediaProductPayload(array_merge([
        'images' => $paths,
        'stock_token' => ProductStockToken::for($product->fresh()),
    ], $picks)))->assertSessionHasNoErrors();

    $update(['primary_image_index' => 2, 'hover_image_index' => 0]);
    expect(savedMediaFlags())->toMatchArray(['primary' => 2, 'hover' => [0]]);
    expect($product->media()->orderBy('sort_order')->pluck('path')->all())->toBe($paths);

    $update(['primary_image_index' => 2, 'hover_image_index' => null]);
    expect(savedMediaFlags())->toMatchArray(['primary' => 2, 'hover' => []]);
});

test('product cards receive the hover choice and search shows the chosen default', function () {
    $this->post(route('admin.products.store'), mediaProductPayload([
        'primary_image_index' => 1,
        'hover_image_index' => 2,
    ]))->assertSessionHasNoErrors();

    $paths = Product::where('slug', 'media-product')->firstOrFail()->media()->orderBy('sort_order')->pluck('path');

    auth('admin')->logout();

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('products.data.0.images.1.is_primary', true)
        ->where('products.data.0.images.2.is_hover', true));

    $this->getJson('/api/search/suggestions?q=Media')
        ->assertJsonPath('products.0.primary_image_url', $paths[1]);
});

test('the main image falls back to the first image when a video is primary', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id]);
    $product->media()->create(['path' => '/clip.mp4', 'type' => 'video', 'is_primary' => true, 'sort_order' => 0]);
    $product->media()->create(['path' => '/one.jpg', 'type' => 'image', 'is_primary' => false, 'sort_order' => 1]);

    expect($product->fresh()->primaryImagePath())->toBe('/one.jpg');
});

test('the migration gives existing products the hover picture their card already swaps to', function () {
    $migration = require glob(database_path('migrations/*_add_is_hover_to_product_media_table.php'))[0];
    $migration->down();

    $newProduct = fn (): Product => Product::factory()->create(['category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id]);
    $insert = fn (Product $product, string $path, string $type, bool $primary): int => DB::table('product_media')->insertGetId([
        'product_id' => $product->id, 'path' => $path, 'type' => $type, 'is_primary' => $primary, 'sort_order' => 0,
    ]);

    $several = $newProduct();
    $insert($several, '/a.jpg', 'image', true);
    $insert($several, '/clip.mp4', 'video', false);
    $hover = $insert($several, '/b.jpg', 'image', false);
    $insert($several, '/c.jpg', 'image', false);
    $insert($newProduct(), '/only.jpg', 'image', true);

    $migration->up();

    expect(DB::table('product_media')->where('is_hover', true)->pluck('id')->all())->toBe([$hover]);
});
