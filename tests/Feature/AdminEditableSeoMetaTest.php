<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-86 (brand pages) and DISC-87 (home, about, contact): optional
 * admin-written search titles and descriptions, printed in the first HTML
 * (production has no SSR, so SSR is forced off) and falling back to what
 * each page showed before when left blank.
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

// ── DISC-87: home, about and contact ────────────────────────────────────────

dataset('cms pages', [
    'home' => ['/', 'home_meta_title', 'home_meta_description'],
    'about' => ['/about', 'about_meta_title', 'about_meta_description'],
    'contact' => ['/contact', 'contact_page_meta_title', 'contact_page_meta_description'],
]);

test('a page uses the SEO title and description the admin saved, once each', function (string $path, string $titleKey, string $descriptionKey) {
    Setting::updateOrCreate(['key' => $titleKey], ['value' => 'Custom & <b>SEO</b> title', 'group' => 'general']);
    Setting::updateOrCreate(['key' => $descriptionKey], ['value' => 'Custom <i>SEO</i> description', 'group' => 'general']);

    $head = seoHead($path);

    expect($head['title'])->toBe(siteTitle('Custom & <b>SEO</b> title'))
        ->and($head['ogTitle'])->toBe(siteTitle('Custom & <b>SEO</b> title'))
        ->and($head['descriptions'])->toBe(['Custom SEO description'])
        ->and($head['ogDescription'])->toBe('Custom SEO description')
        ->and($head['twitterTags'])->toBe(1)
        ->and($head['canonical'])->toBe(url($path));

    $this->get($path)->assertInertia(fn ($page) => $page->where('seo_title', 'Custom & <b>SEO</b> title'));
})->with('cms pages');

test('blank SEO settings fall back to the page\'s usual title and description', function (string $path, string $titleKey, string $descriptionKey, string $title, string $descriptionStart) {
    Setting::updateOrCreate(['key' => $titleKey], ['value' => null, 'group' => 'general']);
    Setting::updateOrCreate(['key' => $descriptionKey], ['value' => '   ', 'group' => 'general']);

    $head = seoHead($path);

    expect($head['title'])->toBe(siteTitle($title))
        ->and($head['descriptions'])->toHaveCount(1)
        ->and($head['descriptions'][0])->toStartWith($descriptionStart);
})->with([
    'home' => ['/', 'home_meta_title', 'home_meta_description', 'Online Shopping in Bangladesh', 'Shop wrist watches, night lights, toys and gifts'],
    'about' => ['/about', 'about_meta_title', 'about_meta_description', 'About Us', 'ShoppyDrive was built'],
    'contact' => ['/contact', 'contact_page_meta_title', 'contact_page_meta_description', 'Contact Us', 'Have a question'],
]);

test('the homepage keeps its business JSON-LD alongside a custom SEO title', function () {
    Setting::updateOrCreate(['key' => 'home_meta_title'], ['value' => 'Shop Online', 'group' => 'homepage']);

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'application/ld+json'))->toBe(1);
});

test('admins can save and clear the SEO text of each page', function (string $path, string $titleKey, string $descriptionKey) {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), [$titleKey => 'Page title', $descriptionKey => 'Page description'])
        ->assertSessionHasNoErrors();

    expect(Setting::where('key', $titleKey)->value('value'))->toBe('Page title')
        ->and(Setting::where('key', $descriptionKey)->value('value'))->toBe('Page description');

    $this->put(route('admin.settings.update'), [$titleKey => '', $descriptionKey => ''])->assertSessionHasNoErrors();

    expect(Setting::where('key', $titleKey)->value('value'))->toBeNull();
})->with('cms pages');

test('a page\'s SEO text is limited to what a search result shows', function (string $path, string $titleKey, string $descriptionKey) {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), [$titleKey => str_repeat('a', 71), $descriptionKey => str_repeat('b', 161)])
        ->assertSessionHasErrors([$titleKey, $descriptionKey]);

    expect(Setting::whereIn('key', [$titleKey, $descriptionKey])->count())->toBe(0);
})->with('cms pages');

test('each page\'s SEO text needs that page\'s own permission', function () {
    $homepageEditor = Admin::create([
        'name' => 'Staff', 'email' => 'homepage-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['homepage' => ['view', 'update']],
    ]);
    actingAs($homepageEditor, 'admin');

    $this->put(route('admin.settings.update'), ['home_meta_title' => 'Shop Online'])->assertSessionHasNoErrors();
    $this->put(route('admin.settings.update'), ['about_meta_title' => 'Hacked'])->assertForbidden();
    $this->put(route('admin.settings.update'), ['contact_page_meta_title' => 'Hacked'])->assertForbidden();

    $aboutEditor = Admin::create([
        'name' => 'Staff', 'email' => 'about-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['about_page' => ['view', 'update']],
    ]);
    actingAs($aboutEditor, 'admin');

    $this->put(route('admin.settings.update'), ['home_meta_title' => 'Hacked'])->assertForbidden();

    expect(Setting::where('key', 'home_meta_title')->value('value'))->toBe('Shop Online')
        ->and(Setting::where('key', 'about_meta_title')->exists())->toBeFalse();
});

test('the admin pages that edit the SEO text receive its saved values', function () {
    actingAs(createAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'home_meta_title'], ['value' => 'Home SEO', 'group' => 'homepage']);
    Setting::updateOrCreate(['key' => 'about_meta_title'], ['value' => 'About SEO', 'group' => 'about_page']);
    Setting::updateOrCreate(['key' => 'contact_page_meta_title'], ['value' => 'Contact SEO', 'group' => 'contact_page']);

    $this->get(route('admin.homepage.index'))->assertInertia(fn ($page) => $page->where('seo.home_meta_title', 'Home SEO'));
    $this->get(route('admin.settings.about-page'))->assertInertia(fn ($page) => $page->where('settings.about_meta_title', 'About SEO'));
    $this->get(route('admin.settings.contact-page'))->assertInertia(fn ($page) => $page->where('settings.contact_page_meta_title', 'Contact SEO'));
});
