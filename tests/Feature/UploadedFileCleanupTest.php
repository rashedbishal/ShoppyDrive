<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\ProductBundle;
use App\Models\PromotionalBanner;
use App\Models\Setting;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** A file on the (fake) public disk, returned as the "/storage/..." value rows store. */
function storedUpload(string $directory): string
{
    $path = $directory.'/'.Str::random(40).'.webp';
    Storage::disk('public')->put($path, 'image bytes');

    return '/storage/'.$path;
}

function uploadExists(string $value): bool
{
    return Storage::disk('public')->exists(substr($value, strlen('/storage/')));
}

/**
 * @return array<string, array{0: Closure(string): Model, 1: string, 2: string}>
 *                                                                               model maker, file column, upload folder
 */
function modelsWithUploads(): array
{
    return [
        'category' => [fn (string $file) => Category::factory()->create(['image' => $file]), 'image', 'categories'],
        'sub-category' => [fn (string $file) => SubCategory::factory()->create(['image' => $file]), 'image', 'sub-categories'],
        'brand' => [fn (string $file) => Brand::factory()->create(['logo' => $file]), 'logo', 'brands'],
        'blog' => [fn (string $file) => Blog::factory()->create(['featured_image' => $file]), 'featured_image', 'blogs'],
        'hero slide' => [fn (string $file) => HeroSlide::create(['title' => 'Sale', 'image' => $file, 'is_active' => true, 'sort_order' => 0]), 'image', 'hero-slides'],
        'promotional banner' => [fn (string $file) => PromotionalBanner::create(['title' => 'Deal', 'image' => $file, 'is_active' => true, 'sort_order' => 0]), 'image', 'promotional-banners'],
        'product bundle' => [fn (string $file) => ProductBundle::factory()->create(['image' => $file]), 'image', 'product-bundles'],
    ];
}

test('replacing an uploaded image removes the old file and keeps the new one', function (Closure $make, string $column, string $directory) {
    $old = storedUpload($directory);
    $model = $make($old);
    $new = storedUpload($directory);

    $model->update([$column => $new]);

    expect(uploadExists($old))->toBeFalse()
        ->and(uploadExists($new))->toBeTrue();
})->with(modelsWithUploads());

test('clearing an image or switching to an outside address removes the old file', function () {
    $first = storedUpload('brands');
    $brand = Brand::factory()->create(['logo' => $first]);

    $brand->update(['logo' => 'https://cdn.example.com/logo.png']);
    expect(uploadExists($first))->toBeFalse();

    $second = storedUpload('categories');
    $category = Category::factory()->create(['image' => $second]);
    $category->update(['image' => null]);
    expect(uploadExists($second))->toBeFalse();
});

test('saving other fields leaves the image alone', function () {
    $file = storedUpload('brands');
    $brand = Brand::factory()->create(['logo' => $file]);

    $brand->update(['name' => 'Renamed']);

    expect(uploadExists($file))->toBeTrue();
});

test('permanently deleting removes the file', function (Closure $make, string $column, string $directory) {
    $file = storedUpload($directory);
    $model = $make($file);

    method_exists($model, 'forceDelete') ? $model->forceDelete() : $model->delete();

    expect(uploadExists($file))->toBeFalse();
})->with(modelsWithUploads());

test('moving a category or sub-category to the trash keeps its image, since it can come back', function () {
    $categoryImage = storedUpload('categories');
    $subCategoryImage = storedUpload('sub-categories');

    Category::factory()->create(['image' => $categoryImage])->delete();
    SubCategory::factory()->create(['image' => $subCategoryImage])->delete();

    expect(uploadExists($categoryImage))->toBeTrue()
        ->and(uploadExists($subCategoryImage))->toBeTrue();
});

test('a file another row still uses is kept', function () {
    $shared = storedUpload('brands');
    $first = Brand::factory()->create(['logo' => $shared]);
    Brand::factory()->create(['logo' => $shared]);

    $first->update(['logo' => storedUpload('brands')]);
    expect(uploadExists($shared))->toBeTrue();

    // Mentioned inside another table's text (e.g. a blog post body) counts too.
    $inPost = storedUpload('categories');
    $category = Category::factory()->create(['image' => $inPost]);
    Blog::factory()->create(['content' => '<p><img src="'.$inPost.'"></p>']);

    $category->forceDelete();
    expect(uploadExists($inPost))->toBeTrue();
});

test('only files inside the upload folders are ever removed', function (string $value) {
    Storage::disk('public')->put('other/keep.webp', 'x');
    $brand = Brand::factory()->create(['logo' => $value]);

    $brand->update(['logo' => storedUpload('brands')]);
    $brand->delete();

    expect(Storage::disk('public')->exists('other/keep.webp'))->toBeTrue();
})->with([
    'a file outside the upload folders' => '/storage/other/keep.webp',
    'a path escaping the folder' => '/storage/brands/../other/keep.webp',
    'a placeholder' => '/placeholder.svg',
]);

test('a save that rolls back keeps the old file', function () {
    $old = storedUpload('brands');
    $brand = Brand::factory()->create(['logo' => $old]);

    try {
        DB::transaction(function () use ($brand): void {
            $brand->update(['logo' => storedUpload('brands')]);

            throw new RuntimeException('Something later in the save failed.');
        });
    } catch (RuntimeException) {
        // Expected.
    }

    expect(uploadExists($old))->toBeTrue();
});

test('bulk-deleting blog posts removes their featured images', function () {
    $admin = createAdmin();
    $images = [storedUpload('blogs'), storedUpload('blogs')];
    $ids = collect($images)->map(fn (string $image) => Blog::factory()->create(['featured_image' => $image])->id)->all();

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.blogs.destroyMany'), ['ids' => $ids])
        ->assertRedirect();

    expect(Blog::whereIn('id', $ids)->exists())->toBeFalse()
        ->and(uploadExists($images[0]))->toBeFalse()
        ->and(uploadExists($images[1]))->toBeFalse();
});

test('replacing a category image from the admin form removes the old file', function () {
    $admin = createAdmin();
    $old = storedUpload('categories');
    $category = Category::factory()->create(['image' => $old]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.categories.update', $category), [
            '_method' => 'PUT',
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => UploadedFile::fake()->image('new.png', 400, 400),
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(uploadExists($old))->toBeFalse()
        ->and(uploadExists($category->refresh()->image))->toBeTrue();
});

test('a mention stored inside JSON, with escaped slashes, still counts as a use', function () {
    $file = storedUpload('brands');
    $brand = Brand::factory()->create(['logo' => $file]);
    // Setting values are JSON-encoded, so the path is stored as "\/storage\/brands\/...".
    Setting::create(['key' => 'promo_image', 'value' => ['image' => $file], 'group' => 'general']);
    expect(Setting::query()->toBase()->where('key', 'promo_image')->value('value'))->toContain('\\/');

    $brand->delete();

    expect(uploadExists($file))->toBeTrue();
});
