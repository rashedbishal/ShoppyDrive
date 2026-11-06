<?php

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/**
 * A photo-like image (noise compresses badly, as real photos do) saved to a
 * temporary file. The left half leans red and the right half blue.
 */
function photoFile(int $width, int $height, string $format = 'jpeg', int $quality = 90): string
{
    $image = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y += 4) {
        for ($x = 0; $x < $width; $x += 4) {
            $noise = random_int(0, 90);
            $color = $x < $width / 2
                ? imagecolorallocate($image, 255 - $noise, $noise, $noise)
                : imagecolorallocate($image, $noise, $noise, 255 - $noise);
            imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'photo');
    match ($format) {
        'jpeg' => imagejpeg($image, $path, $quality),
        'png' => imagepng($image, $path),
    };

    return $path;
}

function uploadOf(string $path, string $name, string $mimeType = 'image/jpeg'): UploadedFile
{
    return new UploadedFile($path, $name, $mimeType, null, true);
}

/**
 * Inserts a minimal big-endian EXIF block, as a phone would write it.
 */
function withExifOrientation(string $path, int $orientation): string
{
    $tiff = "MM\x00*".pack('N', 8).pack('n', 1).pack('nnN', 0x0112, 3, 1).pack('nn', $orientation, 0).pack('N', 0);
    $exif = "Exif\x00\x00".$tiff;
    $jpeg = (string) file_get_contents($path);

    file_put_contents($path, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

    return $path;
}

function storedImage(string $path): GdImage
{
    return imagecreatefromstring((string) Storage::disk('public')->get($path));
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('an uploaded photo is shrunk to the size limit and saved as webp through the real upload route', function () {
    $source = photoFile(4000, 2000);
    actingAs(createAdmin(), 'admin');

    $url = $this->post(route('admin.content-images.store'), ['image' => uploadOf($source, 'camera.jpg')])
        ->assertOk()
        ->json('url');

    $path = str_replace('/storage/', '', $url);
    $stored = storedImage($path);

    expect($url)->toStartWith('/storage/content-images/')->toEndWith('.webp')
        ->and([imagesx($stored), imagesy($stored)])->toBe([ImageOptimizer::MAX_SIDE, 800])
        ->and(Storage::disk('public')->size($path))->toBeLessThan(filesize($source));
});

test('hero slides and banners keep more pixels than other images', function (string $directory, int $expectedWidth) {
    $path = app(ImageOptimizer::class)->store(uploadOf(photoFile(3000, 1000), 'wide.jpg'), $directory);

    expect(imagesx(storedImage($path)))->toBe($expectedWidth);
})->with([
    'hero slide' => ['hero-slides', ImageOptimizer::WIDE_MAX_SIDE],
    'banner' => ['promotional-banners', ImageOptimizer::WIDE_MAX_SIDE],
    'product' => ['products', ImageOptimizer::MAX_SIDE],
]);

test('a sideways phone photo is stored upright', function () {
    $source = withExifOrientation(photoFile(400, 200), 6);

    $stored = storedImage(app(ImageOptimizer::class)->store(uploadOf($source, 'phone.jpg'), 'products'));

    // Orientation 6 means "turn 90° clockwise", so the red left side becomes the top.
    $top = imagecolorsforindex($stored, imagecolorat($stored, 100, 20));
    $bottom = imagecolorsforindex($stored, imagecolorat($stored, 100, 380));

    expect([imagesx($stored), imagesy($stored)])->toBe([200, 400])
        ->and($top['red'])->toBeGreaterThan($top['blue'])
        ->and($bottom['blue'])->toBeGreaterThan($bottom['red']);
});

test('a palette png keeps its transparency', function (int $width, int $expectedWidth) {
    $image = imagecreate($width, 1000);
    imagecolortransparent($image, imagecolorallocate($image, 255, 255, 255));
    $palette = array_map(fn (int $shade): int => imagecolorallocate($image, 200, $shade, $shade), range(0, 200));

    for ($y = 0; $y < 1000; $y++) {
        for ($x = 1000; $x < $width; $x++) {
            imagesetpixel($image, $x, $y, $palette[array_rand($palette)]);
        }
    }

    $source = tempnam(sys_get_temp_dir(), 'palette');
    imagepng($image, $source);

    $path = app(ImageOptimizer::class)->store(uploadOf($source, 'logo.png', 'image/png'), 'brands');
    $stored = storedImage($path);

    expect($path)->toEndWith('.webp')
        ->and(imagesx($stored))->toBe($expectedWidth)
        ->and(imagecolorsforindex($stored, imagecolorat($stored, 100, 100))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($stored, imagecolorat($stored, $expectedWidth - 100, 100))['alpha'])->toBe(0);
})->with([
    'resized' => [2000, ImageOptimizer::MAX_SIDE],
    'already small enough' => [1500, 1500],
]);

test('files it cannot improve are stored exactly as uploaded', function (UploadedFile $file, string $extension) {
    $originalBytes = (string) file_get_contents($file->getRealPath());

    $path = app(ImageOptimizer::class)->store($file, 'products');

    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe($extension)
        ->and(Storage::disk('public')->get($path) === $originalBytes)->toBeTrue();
})->with([
    'gif, which GD would flatten' => fn () => [UploadedFile::fake()->image('animated.gif', 2000, 2000), 'gif'],
    'video' => fn () => [UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'), 'mp4'],
    'a corrupt image' => function () {
        $path = tempnam(sys_get_temp_dir(), 'corrupt');
        file_put_contents($path, "\xFF\xD8\xFF\xE0 not really a jpeg");

        return [uploadOf($path, 'broken.jpg'), 'jpg'];
    },
    'a flat graphic webp would only enlarge' => function () {
        $image = imagecreate(300, 300);
        imagecolorallocate($image, 10, 120, 200);
        $path = tempnam(sys_get_temp_dir(), 'flat');
        imagepng($image, $path);

        return [uploadOf($path, 'flat.png', 'image/png'), 'png'];
    },
]);

test('an image too large to decode in memory is stored as uploaded rather than crashing', function () {
    $source = photoFile(4000, 3000);
    $originalLimit = (string) ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 20 * 1024 * 1024));

    try {
        $path = app(ImageOptimizer::class)->store(uploadOf($source, 'huge.jpg'), 'products');
    } finally {
        ini_set('memory_limit', $originalLimit);
    }

    expect($path)->toEndWith('.jpg')
        ->and(Storage::disk('public')->size($path))->toBe(filesize($source));
});

test('the shrink command only reports unless told to apply', function () {
    Storage::disk('public')->put('products/old.jpg', (string) file_get_contents(photoFile(3000, 1500)));
    $before = Storage::disk('public')->get('products/old.jpg');

    $this->artisan('images:shrink')
        ->expectsOutputToContain('Would shrink 1 image(s)')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('products/old.jpg') === $before)->toBeTrue();
    Storage::disk('local')->assertMissing('image-originals/products/old.jpg');
});

test('applying the shrink keeps each address and format, and backs up the original', function () {
    $disk = Storage::disk('public');
    $disk->put('products/old.jpg', (string) file_get_contents(photoFile(3000, 1500)));
    $disk->put('hero-slides/wide.png', (string) file_get_contents(photoFile(4800, 1600, 'png')));
    chmod($disk->path('products/old.jpg'), 0644);
    $originalJpeg = $disk->get('products/old.jpg');

    $this->artisan('images:shrink', ['--apply' => true])->assertSuccessful();

    expect(getimagesize($disk->path('products/old.jpg')))->toMatchArray([0 => 1600, 1 => 800, 2 => IMAGETYPE_JPEG])
        ->and(getimagesize($disk->path('hero-slides/wide.png')))->toMatchArray([0 => 2400, 1 => 800, 2 => IMAGETYPE_PNG])
        ->and(fileperms($disk->path('products/old.jpg')) & 0777)->toBe(0644)
        ->and($disk->size('products/old.jpg'))->toBeLessThan(strlen($originalJpeg))
        ->and(Storage::disk('local')->get('image-originals/products/old.jpg') === $originalJpeg)->toBeTrue()
        ->and($disk->allFiles())->toHaveCount(2);
});

test('running the shrink again never replaces the backed up original', function () {
    $disk = Storage::disk('public');
    $disk->put('products/old.jpg', (string) file_get_contents(photoFile(3000, 1500)));
    $original = $disk->get('products/old.jpg');

    $this->artisan('images:shrink', ['--apply' => true])->assertSuccessful();
    $afterFirstRun = $disk->get('products/old.jpg');
    $this->artisan('images:shrink', ['--apply' => true])->assertSuccessful();

    expect(Storage::disk('local')->get('image-originals/products/old.jpg') === $original)->toBeTrue()
        ->and(strlen((string) $disk->get('products/old.jpg')))->toBeLessThanOrEqual(strlen($afterFirstRun));
});

test('the shrink leaves small images, videos and unreadable files alone', function () {
    $disk = Storage::disk('public');
    $small = (string) file_get_contents(photoFile(200, 200));
    $disk->put('products/small.jpg', $small);
    $disk->put('products/clip.mp4', 'video bytes');
    $disk->put('products/broken.jpg', str_repeat('not an image', 50000));

    $this->artisan('images:shrink', ['--apply' => true])
        ->expectsOutputToContain('Shrunk 0 image(s)')
        ->assertSuccessful();

    expect($disk->get('products/small.jpg') === $small)->toBeTrue()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});
