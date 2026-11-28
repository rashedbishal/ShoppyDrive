<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/*
 * DISC-98: a product's updated_at is its sitemap lastmod. A save that changes
 * only the product's media must move it; a save that resubmits the same media
 * must not, although every save rebuilds the media rows.
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
function lastModifiedProductPayload(array $overrides = []): array
{
    return array_merge([
        'stock_password' => 'password',
        'name' => 'Dated Product',
        'slug' => 'dated-product',
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
        'media_alts' => ['Front', 'Side', 'Back'],
        'primary_image_index' => 0,
        'hover_image_index' => 1,
    ], $overrides);
}

function lastModifiedProduct(): Product
{
    return Product::where('slug', 'dated-product')->firstOrFail();
}

/**
 * Creates the product a day ago, then saves it now with only the given media
 * fields changed, and returns whether its updated_at moved.
 *
 * @param  Closure(list<string>): array<string, mixed>  $mediaChange  the media fields to submit, given the current paths
 */
function productDateMovesAfter(Closure $mediaChange): bool
{
    test()->travelTo(now()->subDay());
    test()->post(route('admin.products.store'), lastModifiedProductPayload())->assertSessionHasNoErrors();
    $createdAt = lastModifiedProduct()->updated_at;
    test()->travelBack();

    $product = lastModifiedProduct();
    $paths = $product->media()->orderBy('sort_order')->pluck('path')->all();

    test()->put(route('admin.products.update', $product), lastModifiedProductPayload([
        'images' => $paths,
        'stock_token' => ProductStockToken::for($product->fresh()),
        ...$mediaChange($paths),
    ]))->assertSessionHasNoErrors();

    return ! lastModifiedProduct()->updated_at->equalTo($createdAt);
}

test('a save that changes only the media moves the product\'s updated_at', function (Closure $mediaChange) {
    expect(productDateMovesAfter($mediaChange))->toBeTrue();
})->with([
    'an image added' => [fn (array $paths): array => ['images' => [...$paths, UploadedFile::fake()->image('four.jpg')], 'media_alts' => ['Front', 'Side', 'Back', '']]],
    'an image removed' => [fn (array $paths): array => ['images' => [$paths[0], $paths[1]], 'media_alts' => ['Front', 'Side']]],
    'an image replaced' => [fn (array $paths): array => ['images' => [$paths[0], $paths[1], UploadedFile::fake()->image('new.jpg')], 'media_alts' => ['Front', 'Side', 'Back']]],
    'the images reordered' => [fn (array $paths): array => ['images' => [$paths[2], $paths[1], $paths[0]], 'media_alts' => ['Back', 'Side', 'Front'], 'primary_image_index' => 2, 'hover_image_index' => 1]],
    'a new default picture' => [fn (array $paths): array => ['primary_image_index' => 2]],
    'a new hover picture' => [fn (array $paths): array => ['hover_image_index' => 2]],
    'an alt changed' => [fn (array $paths): array => ['media_alts' => ['Front', 'Side', 'Back, open']]],
]);

test('a save that resubmits the same media leaves updated_at alone', function () {
    expect(productDateMovesAfter(fn (array $paths): array => []))->toBeFalse();
});

test('a media-only change writes no audit-log entry', function () {
    $this->post(route('admin.products.store'), lastModifiedProductPayload())->assertSessionHasNoErrors();
    $product = lastModifiedProduct();
    $entriesBefore = Activity::count();

    $this->travel(1)->minutes();
    $this->put(route('admin.products.update', $product), lastModifiedProductPayload([
        'images' => $product->media()->orderBy('sort_order')->pluck('path')->all(),
        'stock_token' => ProductStockToken::for($product->fresh()),
        'primary_image_index' => 2,
    ]))->assertSessionHasNoErrors();

    expect(lastModifiedProduct()->updated_at->equalTo($product->updated_at))->toBeFalse()
        ->and(Activity::count())->toBe($entriesBefore);
});
