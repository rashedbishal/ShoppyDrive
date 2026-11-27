<?php

declare(strict_types=1);

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-86 (brand pages): optional admin-written search titles and
 * descriptions, printed in the first HTML (production has no SSR, so SSR is
 * forced off) and falling back to what each page showed before when left
 * blank.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/**
 * @return array{title: string, descriptions: list<string>, ogTitle: string, ogDescription: string, twitterTags: int, canonical: string}
 */
function seoHead(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();
    preg_match('#<title>([^<]*)</title>#', $html, $title);
    preg_match_all('#<meta name="description" content="([^"]*)"#', $html, $descriptions);
    preg_match('#property="og:title" content="([^"]*)"#', $html, $ogTitle);
    preg_match('#property="og:description" content="([^"]*)"#', $html, $ogDescription);
    preg_match('#rel="canonical" href="([^"]*)"#', $html, $canonical);

    return [
        'title' => html_entity_decode($title[1]),
        'descriptions' => array_map(html_entity_decode(...), $descriptions[1]),
        'ogTitle' => html_entity_decode($ogTitle[1]),
        'ogDescription' => html_entity_decode($ogDescription[1]),
        'twitterTags' => substr_count($html, 'name="twitter:'),
        'canonical' => $canonical[1],
    ];
}

function siteTitle(string $title): string
{
    return $title.' - '.config('app.name');
}

// ── DISC-86: brand pages ────────────────────────────────────────────────────

test('a brand page uses its SEO title and description once, with canonical and share tags unchanged', function () {
    $brand = Brand::factory()->create([
        'name' => 'Acme', 'slug' => 'acme', 'description' => 'Acme tools', 'is_active' => true,
        'meta_title' => 'Acme Tools in Bangladesh', 'meta_description' => 'Genuine Acme tools & spares, delivered nationwide.',
    ]);

    $head = seoHead(route('brand.show', $brand->slug));

    expect($head['title'])->toBe(siteTitle('Acme Tools in Bangladesh'))
        ->and($head['ogTitle'])->toBe(siteTitle('Acme Tools in Bangladesh'))
        ->and($head['descriptions'])->toBe(['Genuine Acme tools & spares, delivered nationwide.'])
        ->and($head['ogDescription'])->toBe('Genuine Acme tools & spares, delivered nationwide.')
        ->and($head['twitterTags'])->toBe(1)
        ->and($head['canonical'])->toBe(route('brand.show', 'acme'));
});

test('a brand without SEO text keeps its name and description', function () {
    $brand = Brand::factory()->create([
        'name' => 'Acme', 'description' => 'Acme tools', 'is_active' => true, 'meta_title' => null, 'meta_description' => null,
    ]);

    $head = seoHead(route('brand.show', $brand->slug));

    expect($head['title'])->toBe(siteTitle('Acme'))
        ->and($head['descriptions'])->toBe(['Acme tools']);
});

test('a brand page passes its SEO title to the browser so the tab title matches', function () {
    $brand = Brand::factory()->create(['is_active' => true, 'meta_title' => 'Acme Tools in Bangladesh']);

    $this->get(route('brand.show', $brand->slug))
        ->assertInertia(fn ($page) => $page->where('brand.meta_title', 'Acme Tools in Bangladesh'));
});

test('admins can save and clear a brand\'s SEO text', function () {
    actingAs(createAdmin(), 'admin');

    $this->post(route('admin.brands.store'), [
        'name' => 'Acme', 'slug' => 'acme', 'meta_title' => 'Acme Tools', 'meta_description' => 'Acme tools description',
    ])->assertSessionHasNoErrors();
    $brand = Brand::where('slug', 'acme')->sole();

    expect($brand)->meta_title->toBe('Acme Tools')->meta_description->toBe('Acme tools description');

    $this->put(route('admin.brands.update', $brand), [
        'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'meta_title' => '', 'meta_description' => '',
    ])->assertSessionHasNoErrors();

    expect($brand->fresh())->meta_title->toBeNull()->meta_description->toBeNull();
});

test('a brand\'s SEO text is limited to what a search result shows', function () {
    actingAs(createAdmin(), 'admin');
    $brand = Brand::factory()->create(['meta_title' => 'Old', 'meta_description' => 'Old']);

    $this->put(route('admin.brands.update', $brand), [
        'name' => $brand->name, 'slug' => $brand->slug, 'meta_title' => str_repeat('a', 71), 'meta_description' => str_repeat('b', 161),
    ])->assertSessionHasErrors(['meta_title', 'meta_description']);

    expect($brand->fresh())->meta_title->toBe('Old')->meta_description->toBe('Old');
});
