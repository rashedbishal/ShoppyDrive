<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\View;

/**
 * IMG-F2/F3: production renders in the browser (no SSR), so the page's main
 * image would only start downloading once the JavaScript has run. The root
 * view prints one <link rel="preload" as="image"> for it instead, so the
 * download starts with the HTML. The srcset/sizes must be the ones the page's
 * <img> uses, or the browser would fetch a second, different file.
 */
final class PreloadImage
{
    /** ProductDetail.tsx's main gallery image: keep both in step (a test checks). */
    public const string PRODUCT_GALLERY_SIZES = '(min-width: 1280px) 720px, (min-width: 1024px) 600px, 100vw';

    /** Home.tsx's hero image or video poster: edge to edge. */
    public const string HERO_SIZES = '100vw';

    /** A null or empty URL clears it, so nothing from an earlier render can linger. */
    public static function share(?string $url, ?string $srcset, string $sizes): void
    {
        View::share('preloadImage', is_string($url) && $url !== ''
            ? ['href' => $url, 'srcset' => $srcset, 'sizes' => $srcset !== null ? $sizes : null]
            : null);
    }
}
