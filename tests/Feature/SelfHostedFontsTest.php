<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-72: the storefront's fonts are served from this site. Google Fonts'
 * stylesheet used to block the first render; Google's files remain only as
 * each @font-face's second source.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

test('pages preload the self-hosted body font instead of loading the google fonts stylesheet', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="preload" href="/fonts/dm-sans-latin.woff2" as="font" type="font/woff2" crossorigin>')
        ->not->toContain('fonts.googleapis.com')
        ->and(public_path('fonts/dm-sans-latin.woff2'))->toBeFile();
});

test('every self-hosted font the stylesheet names exists and is tried before google', function () {
    preg_match_all("~src:\s*url\('(/fonts/[^']+\.woff2)'\) format\('woff2'\),\s*url\('https://fonts\.gstatic\.com/~", (string) file_get_contents(resource_path('css/app.css')), $matches);

    expect($matches[1])->toHaveCount(6);

    foreach ($matches[1] as $path) {
        expect(public_path(ltrim($path, '/')))->toBeFile();
    }
});
