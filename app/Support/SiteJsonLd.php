<?php

declare(strict_types=1);

namespace App\Support;

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
     * @return array{'@context': string, '@graph': array<int, array<string, mixed>>}
     */
    public static function forHome(): array
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

        return ['@context' => 'https://schema.org', '@graph' => [$organization, $website]];
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
