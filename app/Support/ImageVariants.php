<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMG-F5: smaller WebP copies of a stored storefront image, so a phone or a
 * small card doesn't download the full upload. A copy sits beside its
 * original with a fixed name ("abc.png" → "abc-400w.webp"); only widths
 * smaller than the original exist. Nothing is recorded in the database: the
 * page offers a copy (srcset) only when its file is really there, so images
 * from before this existed, or whose copies couldn't be made, keep working
 * exactly as before.
 *
 * Written by ImageOptimizer::storeVariants() (new uploads, the
 * images:variants backfill); removed with the original by
 * UnusedUploadRemover.
 */
final class ImageVariants
{
    /**
     * @var list<int> widths in pixels: thumbnails, cards, phone-width photos,
     *                and 1200 for a full-width photo on a high-density phone
     *                (412 CSS px × 2.6 ≈ 1080 device px)
     */
    public const array WIDTHS = [160, 400, 800, 1200];

    /**
     * An original over this size is never offered in srcset: it's an old,
     * unoptimised upload (e.g. a 2 MB PNG), and a browser that thinks it
     * needs more pixels than the largest copy would otherwise download it.
     */
    private const int LARGEST_ORIGINAL_BYTES = 300 * 1024;

    /** @var list<string> public-disk folders whose images get copies (storefront images only) */
    public const array DIRECTORIES = ['products', 'categories', 'sub-categories', 'hero-slides', 'promotional-banners', 'blogs', 'bundles', 'logos'];

    /** @var array<string, string|null> srcset per URL, for this request */
    private array $srcsets = [];

    public static function variantPath(string $path, int $width): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $path)."-{$width}w.webp";
    }

    public static function isVariant(string $path): bool
    {
        return preg_match('/-('.implode('|', self::WIDTHS).')w\.webp$/', $path) === 1;
    }

    /** Whether copies are made for this public-disk path (a storefront folder, an original, a still-image format). */
    public static function supports(string $path): bool
    {
        return in_array(Str::before($path, '/'), self::DIRECTORIES, true)
            && ! self::isVariant($path)
            && in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    /**
     * The public-disk path behind a "/storage/..." URL, or null for anything
     * else (an external URL, a video, a non-storefront folder).
     */
    public static function publicPath(?string $url): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = substr($url, strlen('/storage/'));

        return ! str_contains($path, '..') && self::supports($path) ? $path : null;
    }

    /**
     * "url-160w.webp 160w, url-400w.webp 400w, url 1254w" for the copies that
     * exist, plus the original at its own width (when it's a reasonable size)
     * so large slots still get full detail; null when there are no copies
     * (the plain src is used).
     */
    public function srcset(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        return $this->srcsets[$url] ??= $this->buildSrcset($url);
    }

    /**
     * IMG-F6: "copy 1x, copy 2x, copy 3x" for an image drawn at a fixed CSS
     * height (the site logo: its width follows the artwork, so width-based
     * srcset can't say which copy is enough). Each density gets the smallest
     * copy at least that many pixels tall; null when only the original would
     * do.
     */
    public function densitySrcset(?string $url, int $cssHeight): ?string
    {
        $path = self::publicPath($url);
        $disk = Storage::disk('public');

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        [$originalWidth, $originalHeight] = (@getimagesize($disk->path($path)) ?: [0, 0]);

        if ($originalWidth <= 0 || $originalHeight <= 0) {
            return null;
        }

        $candidates = [];

        foreach (self::WIDTHS as $width) {
            if ($disk->exists(self::variantPath($path, $width))) {
                $candidates[] = ['/storage/'.self::variantPath($path, $width), (int) round($width * $originalHeight / $originalWidth)];
            }
        }

        $candidates[] = [$url, $originalHeight];
        $entries = [];

        foreach ([1, 2, 3] as $density) {
            $chosen = collect($candidates)->first(fn (array $candidate): bool => $candidate[1] >= $cssHeight * $density) ?? end($candidates);
            $entries[] = $chosen[0]." {$density}x";
        }

        return collect($entries)->every(fn (string $entry): bool => str_starts_with($entry, $url.' ')) ? null : implode(', ', $entries);
    }

    /** Removes an original's copies (the original itself is the caller's business). */
    public static function delete(string $path): void
    {
        $disk = Storage::disk('public');

        foreach (self::WIDTHS as $width) {
            $disk->delete(self::variantPath($path, $width));
        }
    }

    private function buildSrcset(string $url): ?string
    {
        $path = self::publicPath($url);

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $candidates = [];

        foreach (self::WIDTHS as $width) {
            if ($disk->exists(self::variantPath($path, $width))) {
                $candidates[] = '/storage/'.self::variantPath($path, $width)." {$width}w";
            }
        }

        if ($candidates === []) {
            return null;
        }

        $originalWidth = $disk->exists($path) && $disk->size($path) <= self::LARGEST_ORIGINAL_BYTES
            ? (@getimagesize($disk->path($path))[0] ?? null)
            : null;

        if (is_int($originalWidth) && $originalWidth > 0) {
            $candidates[] = $url." {$originalWidth}w";
        }

        return implode(', ', $candidates);
    }
}
