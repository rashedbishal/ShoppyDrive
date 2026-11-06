<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * PERF-004: uploaded photos are saved at a sensible size instead of as they
 * came off a camera. Uses PHP's GD, so there's no new dependency.
 *
 * Anything GD can't safely improve keeps working exactly as before: videos,
 * GIFs (GD would flatten an animation), animated WebP, images too large to
 * decode in the available memory, and hosts without WebP support are all
 * stored as uploaded.
 */
class ImageOptimizer
{
    public const int MAX_SIDE = 1600;

    /** Hero slides and banners run edge to edge, so they keep more pixels. */
    public const int WIDE_MAX_SIDE = 2400;

    /** @var list<string> Public-disk folders holding edge-to-edge images. */
    public const array WIDE_DIRECTORIES = ['hero-slides', 'promotional-banners'];

    private const int WEBP_QUALITY = 80;

    private const int JPEG_QUALITY = 82;

    /** GD holds about 4 bytes per pixel, plus its own overhead. */
    private const int BYTES_PER_PIXEL = 5;

    /**
     * Store an upload on the public disk and return its path there.
     *
     * @throws RuntimeException when the file can't be written.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $maxSide = in_array($directory, self::WIDE_DIRECTORIES, true) ? self::WIDE_MAX_SIDE : self::MAX_SIDE;
        $path = (string) $file->getRealPath();
        $webp = function_exists('imagewebp') ? $this->encode($path, $maxSide, IMAGETYPE_WEBP) : null;

        // A small, already-compressed file can come out larger as WebP.
        if ($webp !== null && strlen($webp) < (int) $file->getSize()) {
            $storedPath = $directory.'/'.Str::random(40).'.webp';

            if (Storage::disk('public')->put($storedPath, $webp)) {
                return $storedPath;
            }
        }

        $storedPath = $file->store($directory, 'public');

        if ($storedPath === false) {
            throw new RuntimeException('An uploaded file could not be stored.');
        }

        return $storedPath;
    }

    /**
     * The image re-encoded in its own format, so a file that already has a
     * public address can be replaced without that address changing. Null when
     * it can't be safely decoded.
     */
    public function reencode(string $absolutePath, int $maxSide): ?string
    {
        $type = @getimagesize($absolutePath)[2] ?? null;

        return $type === null ? null : $this->encode($absolutePath, $maxSide, $type);
    }

    private function encode(string $absolutePath, int $maxSide, int $outputType): ?string
    {
        try {
            $image = $this->load($absolutePath);

            if ($image === null) {
                return null;
            }

            return $this->toBytes($this->fitWithin($image, $maxSide), $outputType);
        } catch (Throwable $exception) {
            Log::warning('[Images] Could not optimize an image', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Upright and in true colour. Palette images must be converted because
     * imagewebp() refuses them.
     */
    private function load(string $absolutePath): ?GdImage
    {
        $info = @getimagesize($absolutePath);

        if ($info === false || ! $this->fitsInMemory($info[0], $info[1])) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolutePath),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $info[2] === IMAGETYPE_JPEG ? $this->applyExifOrientation($image, $absolutePath) : $image;
    }

    /**
     * Running out of memory ends PHP outright, so no catch block could fall
     * back to the original: refuse before decoding.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        return $limit <= 0 || $width * $height * self::BYTES_PER_PIXEL < $limit - memory_get_usage(true);
    }

    /**
     * Phones save a photo sideways and record the turn in EXIF, which GD
     * neither reads nor keeps: without this it would be stored sideways.
     */
    private function applyExifOrientation(GdImage $image, string $absolutePath): GdImage
    {
        $orientation = function_exists('exif_read_data')
            ? (int) ((@exif_read_data($absolutePath) ?: [])['Orientation'] ?? 1)
            : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);

        return $rotated === false ? $image : $rotated;
    }

    private function fitWithin(GdImage $image, int $maxSide): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);

        if ($longestSide <= $maxSide) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $maxSide / $longestSide));
        $newHeight = max(1, (int) round($height * $maxSide / $longestSide));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function toBytes(GdImage $image, int $outputType): ?string
    {
        ob_start();

        $encoded = match ($outputType) {
            IMAGETYPE_WEBP => imagewebp($image, null, self::WEBP_QUALITY),
            IMAGETYPE_JPEG => imagejpeg($image, null, self::JPEG_QUALITY),
            IMAGETYPE_PNG => imagepng($image, null, 9),
            default => false,
        };

        $bytes = (string) ob_get_clean();

        return $encoded && $bytes !== '' ? $bytes : null;
    }
}
