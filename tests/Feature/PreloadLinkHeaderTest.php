<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-51: Hostinger's CDN answers an instant 504 when a response's headers
 * are too large. The uncapped preload Link header listed ~100 files on the
 * redesigned admin dashboard and pushed its headers past that limit.
 */

test('DISC-51: a full dashboard load sends at most 20 preload links, while the page itself still preloads every file', function () {
    actingAs(createMotherAdmin(), 'admin');

    $response = $this->get(route('admin.dashboard'))->assertOk();

    $linkHeader = (string) $response->headers->get('Link');
    $headerLinks = substr_count($linkHeader, 'rel="modulepreload"') + substr_count($linkHeader, 'rel="preload"');
    $pageLinks = substr_count((string) $response->getContent(), 'rel="modulepreload"');

    expect($headerLinks)->toBeGreaterThan(0)->toBeLessThanOrEqual(20)
        ->and(strlen($linkHeader))->toBeLessThan(4096)
        ->and($pageLinks)->toBeGreaterThan(20);
});
