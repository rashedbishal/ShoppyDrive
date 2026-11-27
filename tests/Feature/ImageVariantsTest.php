<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ImageOptimizer;
use App\Support\ImageVariants;
use App\Support\ProductCardData;
use App\Support\UnusedUploadRemover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * IMG-F5 / IMG-F6: smaller WebP copies of storefront images, written beside
 * the original at upload, offered to the browser (srcset) only when their
 * files really exist, and removed with the original.
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/** A real image file of the given size, as an upload. */
function variantUpload(int $width, int $height, string $name = 'photo.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height);
}

/** The bytes of a real image file (the upload is kept alive while it's read). */
function variantBytes(int $width, int $height, string $name = 'photo.jpg'): string
{
    $file = variantUpload($width, $height, $name);

    return (string) file_get_contents($file->getRealPath());
}

/** @return array{0: int, 1: int} */
function variantSize(string $path): array
{
    $size = getimagesizefromstring((string) Storage::disk('public')->get($path));

    return [$size[0], $size[1]];
}

test('a storefront upload gets 160, 400, 800 and 1200 px WebP copies, each exactly that wide', function () {
    $path = app(ImageOptimizer::class)->store(variantUpload(1600, 1200), 'products');

    foreach ([160 => 120, 400 => 300, 800 => 600, 1200 => 900] as $width => $height) {
        $copy = ImageVariants::variantPath($path, $width);

        expect(Storage::disk('public')->exists($copy))->toBeTrue()
            ->and(variantSize($copy))->toBe([$width, $height])
            ->and(getimagesizefromstring((string) Storage::disk('public')->get($copy))['mime'])->toBe('image/webp');
    }
});

test('only copies narrower than the original are made, and never for other folders, videos or GIFs', function () {
    $small = app(ImageOptimizer::class)->store(variantUpload(300, 300), 'products');
    $avatar = app(ImageOptimizer::class)->store(variantUpload(1200, 1200), 'avatars');
    $gif = app(ImageOptimizer::class)->store(UploadedFile::fake()->image('anim.gif', 1200, 1200), 'products');

    expect(Storage::disk('public')->exists(ImageVariants::variantPath($small, 160)))->toBeTrue()
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath($small, 400)))->toBeFalse()
        ->and(collect(Storage::disk('public')->allFiles('avatars'))->filter(ImageVariants::isVariant(...)))->toBeEmpty()
        ->and(collect(Storage::disk('public')->allFiles('products'))->filter(fn (string $file): bool => str_starts_with($file, preg_replace('/\.\w+$/', '', $gif).'-')))->toBeEmpty();
});

test('a file that is not really an image is stored as before, with no copies and no error', function () {
    Storage::disk('public')->put('products/broken.jpg', 'not an image');

    expect(app(ImageOptimizer::class)->storeVariants('products/broken.jpg'))->toBe(0)
        ->and(Storage::disk('public')->allFiles('products'))->toBe(['products/broken.jpg']);
});

test('srcset lists the copies that exist plus the original at its own width', function () {
    $path = app(ImageOptimizer::class)->store(variantUpload(1600, 1200), 'products');
    $url = '/storage/'.$path;
    $base = '/storage/'.preg_replace('/\.\w+$/', '', $path);

    expect(app(ImageVariants::class)->srcset($url))
        ->toBe("{$base}-160w.webp 160w, {$base}-400w.webp 400w, {$base}-800w.webp 800w, {$base}-1200w.webp 1200w, {$url} 1600w");
});

test('an oversized old original (e.g. a 2 MB PNG) is left out of srcset, so phones get the 1200 px copy instead', function () {
    Storage::disk('public')->put('products/heavy.png', variantBytes(1500, 1500, 'heavy.png').str_repeat("\0", 400 * 1024));
    app(ImageOptimizer::class)->storeVariants('products/heavy.png');

    $srcset = (string) app(ImageVariants::class)->srcset('/storage/products/heavy.png');

    expect($srcset)->toEndWith('/storage/products/heavy-1200w.webp 1200w')
        ->not->toContain('/storage/products/heavy.png');
});

test('srcset is null whenever only the plain image can be used', function (?string $url) {
    Storage::disk('public')->put('products/old.jpg', variantBytes(900, 900));

    expect(app(ImageVariants::class)->srcset($url))->toBeNull();
})->with([
    'no copies yet (uploaded before)' => '/storage/products/old.jpg',
    'external URL' => 'https://cdn.example.com/products/a.jpg',
    'not a storefront folder' => '/storage/avatars/a.jpg',
    'path traversal' => '/storage/products/../.env',
    'a video' => '/storage/products/clip.mp4',
    'nothing' => null,
]);

test('product media, card data, categories expose their srcset; a video has none', function () {
    $path = app(ImageOptimizer::class)->store(variantUpload(1000, 1000), 'products');
    $product = Product::factory()->create();
    $image = ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/'.$path, 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);
    $video = ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/'.$path, 'type' => 'video', 'sort_order' => 1]);
    $category = Category::factory()->create(['image' => '/storage/'.app(ImageOptimizer::class)->store(variantUpload(1000, 500), 'categories')]);

    expect($image->toArray()['srcset'])->toContain('-400w.webp 400w')
        ->and($video->toArray()['srcset'])->toBeNull()
        ->and(ProductCardData::make($product->fresh(['images']))['images'][0]['srcset'])->toContain('-160w.webp 160w')
        ->and($category->toArray()['image_srcset'])->toContain('-800w.webp 800w');
});

test('the logo gets the smallest copy tall enough for each screen density', function () {
    Storage::disk('public')->put('logos/logo.png', variantBytes(658, 576, 'logo.png'));
    app(ImageOptimizer::class)->storeVariants('logos/logo.png');

    // 160w is 140 px tall, 400w is 350 px: 48 px needs 48/96/144.
    expect(app(ImageVariants::class)->densitySrcset('/storage/logos/logo.png', 48))
        ->toBe('/storage/logos/logo-160w.webp 1x, /storage/logos/logo-160w.webp 2x, /storage/logos/logo-400w.webp 3x');
});

test('a logo with no copies, or too small to need them, has no density srcset', function () {
    Storage::disk('public')->put('logos/tiny.png', variantBytes(120, 40, 'tiny.png'));

    expect(app(ImageVariants::class)->densitySrcset('/storage/logos/tiny.png', 48))->toBeNull();

    app(ImageOptimizer::class)->storeVariants('logos/tiny.png');

    expect(app(ImageVariants::class)->densitySrcset('/storage/logos/tiny.png', 48))->toBeNull();
});

test('removing an unused upload removes its copies too', function () {
    $path = app(ImageOptimizer::class)->store(variantUpload(1200, 900), 'products');

    app(UnusedUploadRemover::class)->removeIfUnused('/storage/'.$path);

    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});
