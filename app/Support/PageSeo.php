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
    public const string DEFAULT_DESCRIPTION = 'Premium e-commerce destination in Bangladesh for electronics, lifestyle, and more.';

    private const string DEFAULT_IMAGE = '/images/brand/og-image.jpg';

    private const int MAX_DESCRIPTION_LENGTH = 160;

    /**
     * Set this response's tags. Blank values fall back to the site defaults.
     *
     * @param  array<string, mixed>|null  $jsonLd
     * @param  array<string, mixed>|null  $breadcrumbs  a breadcrumbList() result
     */
    public static function share(string $title, ?string $description = null, ?string $image = null, string $type = 'website', ?array $jsonLd = null, ?array $breadcrumbs = null): void
    {
        View::share('seo', [
            'title' => self::title($title),
            'description' => $description,
            'image' => $image,
            'type' => $type,
            'jsonLd' => $jsonLd,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * DISC-83: a schema.org BreadcrumbList, printed by the root view as its
     * own JSON-LD block next to the page's main one. Positions follow the
     * given order, starting at 1.
     *
     * @param  list<array{0: string, 1: string}>  $crumbs  [name, absolute URL] pairs, from Home to this page
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * DISC-76/77: the one title format, "Page - Site", matching what the
     * browser's Inertia title callback (app.tsx) shows after JavaScript runs.
     * DISC-95: a title that already names the site (an SEO title saved as
     * "Watches | ShoppyDrive") is kept as it is rather than getting a second
     * " - ShoppyDrive"; app.tsx applies the same rule.
     */
    public static function title(string $title): string
    {
        $title = trim($title);
        $siteName = (string) config('app.name');

        if ($title === '') {
            return $siteName;
        }

        if ($siteName !== '' && Str::contains($title, $siteName, ignoreCase: true)) {
            return $title;
        }

        return $title.' - '.$siteName;
    }

    /**
     * DISC-95: a page's own content used as its description when no search
     * description was saved. Category and brand descriptions are often
     * written as a heading line ("Toys in Bangladesh | Shop Online") over the
     * text itself; that heading would otherwise run straight into the first
     * sentence, so it's left out when real text follows it.
     */
    public static function descriptionFromContent(?string $content): ?string
    {
        $lines = array_values(array_filter(
            array_map(trim(...), preg_split('/\R/u', self::plainText($content)) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        if (count($lines) > 1 && self::isHeadingLine($lines[0])) {
            array_shift($lines);
        }

        $text = implode(' ', $lines);

        return $text === '' ? null : $text;
    }

    public static function shareForPage(Page $page): void
    {
        self::share($page->meta_title ?: $page->title, $page->meta_description);
    }

    /**
     * What the root view prints, with every default applied.
     *
     * @param  array<string, mixed>  $seo
     * @return array{title: string, description: string, canonical: string|null, image: string, type: string, jsonLd: array<string, mixed>|null, breadcrumbs: array<string, mixed>|null}
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
            'breadcrumbs' => $seo['breadcrumbs'] ?? null,
        ];
    }

    /**
     * DISC-95: plain text, the same sentence pasted twice in a row said once,
     * and cut at the end of a sentence (or at least of a word) rather than
     * mid-word.
     */
    private static function description(?string $description): string
    {
        $text = Str::squish(self::plainText($description));
        $text = self::withoutRepeatedSentences($text);

        return self::shorten($text !== '' ? $text : self::DEFAULT_DESCRIPTION);
    }

    /**
     * Rich text without its markup: block ends become line breaks (so two
     * paragraphs don't run together into one word) and entities such as
     * &amp; are decoded once, because the view escapes the result again.
     */
    private static function plainText(?string $html): string
    {
        $text = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|tr|blockquote)>#i', "\n", (string) $html) ?? '';

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** A short line without closing punctuation: a heading, not a sentence. */
    private static function isHeadingLine(string $line): bool
    {
        return mb_strlen($line) <= 90 && ! preg_match('/[.!?।:]$/u', $line);
    }

    private static function withoutRepeatedSentences(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?।])\s+/u', $text) ?: [$text];
        $kept = [];

        foreach ($sentences as $sentence) {
            if ($kept === [] || mb_strtolower(end($kept)) !== mb_strtolower($sentence)) {
                $kept[] = $sentence;
            }
        }

        return implode(' ', $kept);
    }

    private static function shorten(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_DESCRIPTION_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_DESCRIPTION_LENGTH);

        // A whole sentence that fills at least half the space reads better than a cut one.
        if (preg_match('/^.*[.!?।](?=\s)/us', $cut.' ', $match) && mb_strlen($match[0]) >= self::MAX_DESCRIPTION_LENGTH / 2) {
            return $match[0];
        }

        $lastSpace = mb_strrpos($cut, ' ');
        $words = $lastSpace !== false && $lastSpace > 0 ? mb_substr($cut, 0, $lastSpace) : mb_substr($cut, 0, self::MAX_DESCRIPTION_LENGTH - 1);

        // Unicode-aware: a byte-based rtrim() with "–—" in its list would
        // strip the last byte off a Bangla letter that ends in the same byte.
        return (preg_replace('/[\s,;:|\-–—]+$/u', '', $words) ?? $words).'…';
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
