<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Blog;
use Illuminate\Support\Str;

/**
 * DISC-61: the homepage's JSON-LD, so search engines and AI agents can tell
 * what business this is. Built only from Admin › Settings values that are
 * actually saved: the storefront's display fallbacks (e.g. the placeholder
 * phone number) are never published as facts. Printed by app.blade.php
 * through PageSeo, like the product pages' JSON-LD.
 */
class SiteJsonLd
{
    private const array SOCIAL_NETWORKS = ['facebook', 'instagram', 'twitter', 'youtube', 'tiktok'];

    /**
     * DISC-85: whole words that name a group or company rather than a
     * person, so such an author is an Organization.
     */
    private const array ORGANIZATION_WORDS = [
        'team', 'staff', 'editorial', 'newsroom', 'desk', 'agency', 'media', 'group', 'company',
        'corporation', 'corp', 'ltd', 'limited', 'inc', 'llc', 'plc', 'store', 'shop',
    ];

    /**
     * DISC-85: whole words that name a role, not anyone in particular, so
     * such an author is left out rather than guessed.
     */
    private const array ROLE_WORDS = ['admin', 'administrator', 'editor', 'author', 'writer', 'guest', 'moderator', 'contributor'];

    /** Bounds the page size; the homepage rarely shows more. */
    private const int MAX_PRODUCTS = 50;

    /**
     * @param  array<int, array<string, mixed>>  $productCards  the cards the homepage shows (ProductCardData + flash price), in page order
     * @return array{'@context': string, '@graph': array<int, array<string, mixed>>}
     */
    public static function forHome(array $productCards = []): array
    {
        $settings = LayoutCache::settings();
        $home = url('/');

        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => self::text($settings['site_name'] ?? null) ?? config('app.name'),
            'legalName' => self::text($settings['legal_business_name'] ?? null),
            'url' => $home,
            'logo' => self::absoluteUrl($settings['logo'] ?? null),
            'email' => self::text($settings['contact_email'] ?? null),
            'telephone' => self::text($settings['contact_phone'] ?? null),
            'address' => self::address($settings['contact_address'] ?? null),
            'sameAs' => self::sameAs($settings['social_links'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        $website = [
            '@type' => 'WebSite',
            '@id' => $home.'#website',
            'name' => $organization['name'],
            'url' => $home,
            'publisher' => ['@id' => $organization['@id']],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $graph = [$organization, $website];
        $products = self::productList($productCards);

        if ($products !== null) {
            $graph[] = $products;
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * DISC-84: a published blog post as a BlogPosting. Only values the post
     * really has are included: no published date, image, description or
     * author is made up when it is blank. The publisher is the same
     * Organization the homepage describes (same @id, saved settings only).
     *
     * @return array<string, mixed>
     */
    public static function forBlogPost(Blog $blog): array
    {
        $settings = LayoutCache::settings();
        $url = route('blog.show', $blog->slug);
        $description = self::text(strip_tags((string) ($blog->meta_description ?: $blog->excerpt)));
        $image = self::absoluteUrl($blog->featured_image);
        $publisher = array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => self::text($settings['site_name'] ?? null) ?? config('app.name'),
            'url' => url('/'),
            'logo' => self::absoluteUrl($settings['logo'] ?? null),
        ], fn (mixed $value): bool => $value !== null);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => self::text($blog->title),
            'description' => $description,
            'url' => $url,
            'mainEntityOfPage' => $url,
            'image' => $image !== null ? [$image] : null,
            'datePublished' => $blog->published_at?->toIso8601String(),
            'dateModified' => $blog->updated_at?->toIso8601String(),
            'author' => self::blogAuthor(self::text($blog->author_name), $settings, $publisher),
            'publisher' => $publisher,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * DISC-85: the CMS's Author Name is free text (its placeholder suggests
     * "ShoppyDrive Team"), so its schema type is decided, in this order:
     * the shop's own saved name in it means the shop itself (the publisher
     * entity); a bare role ("Admin", "Editor") names nobody, so it is left
     * out; a group/company word ("Team", "Ltd") makes it an Organization; a
     * personal-name shape (2-4 words of letters, initials allowed) makes it a
     * Person. Anything else is left out rather than mislabelled.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $publisher
     * @return array<string, mixed>|null
     */
    private static function blogAuthor(?string $name, array $settings, array $publisher): ?array
    {
        if ($name === null) {
            return null;
        }

        $shopNames = array_filter([
            self::text($settings['site_name'] ?? null),
            self::text($settings['legal_business_name'] ?? null),
            self::text(config('app.name')),
        ], fn (?string $shopName): bool => $shopName !== null && mb_strlen($shopName) >= 3);

        foreach ($shopNames as $shopName) {
            if (mb_stripos($name, $shopName) !== false) {
                return array_intersect_key($publisher, array_flip(['@type', '@id', 'name', 'url']));
            }
        }

        $words = preg_split('/[^\p{L}\p{M}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (array_intersect($words, self::ROLE_WORDS) !== []) {
            return null;
        }

        if (array_intersect($words, self::ORGANIZATION_WORDS) !== []) {
            return ['@type' => 'Organization', 'name' => $name];
        }

        if (preg_match("/^[\p{L}\p{M}][\p{L}\p{M}.'’-]*(?: [\p{L}\p{M}][\p{L}\p{M}.'’-]*){1,3}$/u", $name) === 1) {
            return ['@type' => 'Person', 'name' => $name];
        }

        return null;
    }

    /**
     * DISC-64: the homepage's products as Product + Offer, so agents can
     * read prices without JavaScript. Each price is the one its card shows:
     * the flash-sale price while one applies, else the product price.
     *
     * @param  array<int, array<string, mixed>>  $productCards
     * @return array<string, mixed>|null
     */
    private static function productList(array $productCards): ?array
    {
        $items = collect($productCards)
            ->filter(fn (mixed $card): bool => is_array($card) && filled($card['slug'] ?? null) && is_numeric($card['price'] ?? null))
            ->unique('id')
            ->take(self::MAX_PRODUCTS)
            ->values()
            ->map(fn (array $card, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => self::product($card),
            ]);

        return $items->isEmpty() ? null : [
            '@type' => 'ItemList',
            'name' => 'Products on the homepage',
            'numberOfItems' => $items->count(),
            'itemListElement' => $items->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private static function product(array $card): array
    {
        $url = route('product.show', $card['slug']);
        $flashPrice = ($card['in_flash_sale'] ?? false) && is_numeric($card['flash_sale_price'] ?? null) ? $card['flash_sale_price'] : null;

        return array_filter([
            '@type' => 'Product',
            'name' => self::text($card['name'] ?? null),
            'url' => $url,
            'image' => self::absoluteUrl($card['primary_image_url'] ?? null),
            'sku' => self::text($card['sku'] ?? null),
            'brand' => ($brand = self::text($card['brand']['name'] ?? null)) !== null ? ['@type' => 'Brand', 'name' => $brand] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'BDT',
                'price' => number_format((float) ($flashPrice ?? $card['price']), 2, '.', ''),
                'availability' => (float) ($card['available_stock'] ?? 0) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? Str::squish($value) : '';

        return $text !== '' ? $text : null;
    }

    private static function absoluteUrl(mixed $path): ?string
    {
        $path = self::text($path);

        if ($path === null) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : url($path);
    }

    /**
     * @return array{'@type': string, streetAddress: string, addressCountry: string}|null
     */
    private static function address(mixed $value): ?array
    {
        $address = self::text($value);

        return $address === null ? null : [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressCountry' => 'BD',
        ];
    }

    /**
     * The saved social profile links, as absolute http(s) URLs only.
     *
     * @return array<int, string>
     */
    private static function sameAs(mixed $socialLinks): array
    {
        if (! is_array($socialLinks)) {
            return [];
        }

        return collect(self::SOCIAL_NETWORKS)
            ->map(fn (string $network): ?string => self::text($socialLinks[$network] ?? null))
            ->filter(fn (?string $link): bool => $link !== null && Str::startsWith($link, ['http://', 'https://']))
            ->values()
            ->all();
    }
}
