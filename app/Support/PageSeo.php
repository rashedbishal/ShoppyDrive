<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\HidePrivatePagesFromSearchEngines;
use App\Models\Page;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * FE-009: the tags crawlers read. Production has no SSR, so Inertia's <Head>
 * only runs in a visitor's browser and never reaches Facebook's scraper or
 * curl; everything here goes through the Blade root view instead, as FE-006
 * first did for product pages.
 */
class PageSeo
{
    private const string DEFAULT_DESCRIPTION = 'Premium e-commerce destination in Bangladesh for electronics, lifestyle, and more.';

    private const string DEFAULT_IMAGE = '/images/brand/og-image.jpg';

    private const int MAX_DESCRIPTION_LENGTH = 160;

    /**
     * Set this response's tags. Blank values fall back to the site defaults.
     */
    public static function share(string $title, ?string $description = null, ?string $image = null, string $type = 'website'): void
    {
        View::share('seo', [
            'title' => $title.' - '.config('app.name'),
            'description' => $description,
            'image' => $image,
            'type' => $type,
        ]);
    }

    public static function shareForPage(Page $page): void
    {
        self::share($page->meta_title ?: $page->title, $page->meta_description);
    }

    /**
     * What the root view prints, with every default applied.
     *
     * @param  array<string, mixed>  $seo
     * @return array{title: string, description: string, canonical: string|null, image: string, type: string, jsonLd: array<string, mixed>|null}
     */
    public static function tags(array $seo): array
    {
        return [
            'title' => (string) ($seo['title'] ?? config('app.name')),
            'description' => self::description($seo['description'] ?? null),
            'canonical' => $seo['canonical'] ?? self::canonical(),
            'image' => self::absoluteImage($seo['image'] ?? null),
            'type' => (string) ($seo['type'] ?? 'website'),
            'jsonLd' => $seo['jsonLd'] ?? null,
        ];
    }

    private static function description(?string $description): string
    {
        $text = Str::squish(strip_tags((string) $description));

        return Str::limit($text !== '' ? $text : self::DEFAULT_DESCRIPTION, self::MAX_DESCRIPTION_LENGTH);
    }

    /**
     * Sorted or filtered URLs all point at the plain page, so they don't
     * compete with it. Later pages of a list keep their own number because
     * they show different products.
     */
    private static function canonical(): ?string
    {
        $request = request();

        if (HidePrivatePagesFromSearchEngines::isPrivate($request)) {
            return null;
        }

        $page = $request->integer('page');

        return $request->url().($page > 1 ? '?page='.$page : '');
    }

    /**
     * Share previews can't show SVG, which is what the placeholder is.
     */
    private static function absoluteImage(?string $image): string
    {
        $path = blank($image) || Str::endsWith($image, '.svg') ? self::DEFAULT_IMAGE : $image;

        return Str::startsWith($path, ['http://', 'https://']) ? $path : url($path);
    }
}
