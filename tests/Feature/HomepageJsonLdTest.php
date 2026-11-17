<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-55: the homepage tells search engines and AI agents what business
 * this is, from saved Admin › Settings values only. Production has no SSR,
 * so the Blade root view's first response is what they read.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** @return array{0: string, 1: array<string, mixed>} the page HTML and its decoded JSON-LD. */
function homepageJsonLd(): array
{
    $html = test()->get(route('home'))->assertOk()->getContent();

    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match))->toBe(1);

    return [$html, json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)];
}

function saveSetting(string $key, mixed $value): void
{
    Setting::create(['key' => $key, 'value' => $value, 'group' => 'general']);
}

test('the homepage describes the business and the site from saved settings', function () {
    saveSetting('site_name', 'ShoppyDrive');
    saveSetting('legal_business_name', 'ShoppyDrive Ltd.');
    saveSetting('logo', '/storage/settings/logo.png');
    saveSetting('contact_email', 'help@example.com');
    saveSetting('contact_phone', '+8801711000000');
    saveSetting('contact_address', 'House 1, Road 2, Dhaka');
    saveSetting('social_links', [
        'facebook' => 'https://facebook.com/shoppydrive',
        'instagram' => '',
        'youtube' => 'https://youtube.com/@shoppydrive',
        'tiktok' => 'not-a-url',
    ]);

    [, $jsonLd] = homepageJsonLd();
    [$organization, $website] = $jsonLd['@graph'];

    expect($jsonLd['@context'])->toBe('https://schema.org');
    expect($organization)->toBe([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => 'ShoppyDrive',
        'legalName' => 'ShoppyDrive Ltd.',
        'url' => url('/'),
        'logo' => url('/storage/settings/logo.png'),
        'email' => 'help@example.com',
        'telephone' => '+8801711000000',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'House 1, Road 2, Dhaka', 'addressCountry' => 'BD'],
        'sameAs' => ['https://facebook.com/shoppydrive', 'https://youtube.com/@shoppydrive'],
    ]);
    expect($website['@type'])->toBe('WebSite');
    expect($website['publisher'])->toBe(['@id' => url('/').'#organization']);
    expect($website['potentialAction']['target']['urlTemplate'])->toBe(url('/search').'?q={search_term_string}');
});

test('unsaved contact details are left out instead of the storefront placeholders, and the page tags are unchanged', function () {
    [$html, $jsonLd] = homepageJsonLd();

    expect($jsonLd['@graph'][0])->toBe([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => config('app.name'),
        'url' => url('/'),
    ]);
    expect($html)->toContain('<title>'.config('app.name').'</title>');
});

test('a business name with </script> or HTML cannot break out of the JSON-LD script', function () {
    $name = 'Shop "A" & B </script><script>alert(1)</script>';
    saveSetting('site_name', $name);

    [$html, $jsonLd] = homepageJsonLd();

    expect($html)->not->toContain('<script>alert(1)</script>');
    expect($jsonLd['@graph'][0]['name'])->toBe($name);
});
