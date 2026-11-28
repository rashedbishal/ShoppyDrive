<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/*
 * DISC-98: each product image's alt text is saved from the admin form
 * (media_alts, by position like media_tags) and stays with its own image
 * although every save rebuilds the media rows.
 */

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
function altTextProductPayload(array $overrides = []): array
{
    return array_merge([
        'stock_password' => 'password',
        'name' => 'Alt Product',
        'slug' => 'alt-product',
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

function altTextProduct(): Product
{
    return Product::where('slug', 'alt-product')->firstOrFail();
}

/** @return list<string|null> each image's alt, in gallery order */
function savedAlts(): array
{
    return altTextProduct()->media()->orderBy('sort_order')->pluck('alt')->all();
}

/**
 * Creates the product with the given alts and returns its image paths in
 * gallery order.
 *
 * @param  list<string|null>  $alts
 * @return list<string>
 */
function createProductWithAlts(array $alts): array
{
    test()->post(route('admin.products.store'), altTextProductPayload(['media_alts' => $alts]))->assertSessionHasNoErrors();

    return altTextProduct()->media()->orderBy('sort_order')->pluck('path')->all();
}

/** @param  array<string, mixed>  $overrides */
function updateAltTextProduct(array $overrides): void
{
    test()->put(route('admin.products.update', altTextProduct()), altTextProductPayload([
        'stock_token' => ProductStockToken::for(altTextProduct()->fresh()),
        ...$overrides,
    ]))->assertSessionHasNoErrors();
}

test('alt text is saved as plain text: trimmed, whitespace collapsed, tags removed, empty stored as null', function () {
    createProductWithAlts(["  Gift   box,\n open  ", '<b>Back</b> view, 5 < 6 cm', '   ']);

    expect(savedAlts())->toBe(['Gift box, open', 'Back view, 5 < 6 cm', null]);
});

test('alt text survives the next save, whether or not the form sends it again', function () {
    $paths = createProductWithAlts(['Front', 'Side', 'Back']);

    updateAltTextProduct(['images' => $paths, 'media_alts' => ['Front', 'Side', 'Back']]);
    expect(savedAlts())->toBe(['Front', 'Side', 'Back']);

    // A form opened before alt text existed sends no media_alts at all.
    updateAltTextProduct(['images' => $paths]);
    expect(savedAlts())->toBe(['Front', 'Side', 'Back']);
});

test('alt text stays with its own image when images are removed or added', function () {
    [$front, $side, $back] = createProductWithAlts(['Front', 'Side', 'Back']);

    updateAltTextProduct(['images' => [$side, $back], 'media_alts' => ['Side', 'Back']]);
    expect(altTextProduct()->media()->pluck('alt', 'path')->all())->toBe([$side => 'Side', $back => 'Back']);

    updateAltTextProduct([
        'images' => [$side, $back, UploadedFile::fake()->image('four.jpg')],
        'media_alts' => ['Side', 'Back', 'Gift box'],
    ]);
    expect(savedAlts())->toBe(['Side', 'Back', 'Gift box']);
});

test('a missing alt for one item keeps that image\'s own alt, matched by path, not the alt of its old position', function () {
    [$front, $side, $back] = createProductWithAlts(['Front', 'Side', 'Back']);

    updateAltTextProduct(['images' => [$back, $front], 'media_alts' => [1 => 'Front, updated']]);

    expect(altTextProduct()->media()->orderBy('sort_order')->pluck('alt', 'path')->all())
        ->toBe([$back => 'Back', $front => 'Front, updated']);
});

test('changing the default or hover picture keeps every alt in place', function () {
    $paths = createProductWithAlts(['Front', 'Side', 'Back']);

    updateAltTextProduct([
        'images' => $paths,
        'media_alts' => ['Front', 'Side', 'Back'],
        'primary_image_index' => 2,
        'hover_image_index' => 0,
    ]);

    $media = altTextProduct()->media()->orderBy('sort_order')->get();
    expect($media->pluck('alt')->all())->toBe(['Front', 'Side', 'Back'])
        ->and($media->pluck('is_primary')->all())->toBe([false, false, true])
        ->and($media->pluck('is_hover')->all())->toBe([true, false, false]);
});

test('an alt the admin empties is cleared', function () {
    $paths = createProductWithAlts(['Front', 'Side', 'Back']);

    updateAltTextProduct(['images' => $paths, 'media_alts' => ['Front', '', 'Back']]);

    expect(savedAlts())->toBe(['Front', null, 'Back']);
});

test('alt text may be up to 125 characters', function () {
    $this->post(route('admin.products.store'), altTextProductPayload(['media_alts' => [str_repeat('a', 126)]]))
        ->assertSessionHasErrors('media_alts.0');
    expect(Product::where('slug', 'alt-product')->exists())->toBeFalse();

    createProductWithAlts([str_repeat('a', 125)]);
    expect(savedAlts()[0])->toBe(str_repeat('a', 125));
});

test('the placeholder picture never keeps alt text, so a renamed product is not described by its old name', function () {
    $product = Product::factory()->create([
        'name' => 'Old Name',
        'slug' => 'alt-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
    ]);
    // As saved before DISC-98 (and by the seeders): the product name copied onto the placeholder.
    $product->media()->create(['path' => '/placeholder.svg', 'type' => 'image', 'alt' => 'Old Name', 'is_primary' => true, 'sort_order' => 0]);

    updateAltTextProduct(['name' => 'New Name', 'images' => ['/placeholder.svg'], 'media_alts' => ['Old Name']]);

    expect(altTextProduct()->media()->pluck('alt', 'path')->all())->toBe(['/placeholder.svg' => null]);
});
