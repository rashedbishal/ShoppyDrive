<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** The <title> the first HTML response carries, without the " - Site" suffix. */
function serverTitle(string $url): string
{
    preg_match('#<title>([^<]*)</title>#', test()->get($url)->assertOk()->getContent(), $title);

    return html_entity_decode(substr($title[1], 0, -strlen(' - '.config('app.name'))));
}

// ── DISC-88: the browser title matches the server title ─────────────────────

test('a category or sub-category page gives the browser the same title the server printed', function (?string $metaTitle, string $expected) {
    $parent = Category::factory()->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true, 'meta_title' => $metaTitle]);
    SubCategory::factory()->create(['name' => 'Pots', 'slug' => 'pots', 'category_id' => $parent->id, 'is_active' => true, 'meta_title' => $metaTitle]);

    foreach (['kitchen' => 'Kitchen', 'pots' => 'Pots'] as $slug => $name) {
        $title = str_replace('{name}', $name, $expected);

        expect(serverTitle(route('category.show', $slug)))->toBe($title);
        $this->get(route('category.show', $slug))->assertInertia(fn ($page) => $page
            ->where('seoTitle', $title)
            ->where('title', $name));
    }
})->with([
    'saved SEO title' => ['Cookware & Pans in Bangladesh', 'Cookware & Pans in Bangladesh'],
    'no SEO title' => [null, '{name}'],
]);

test('the all-products listing keeps its own title and sends no SEO title override', function () {
    expect(serverTitle(route('products.index')))->toBe('All Products');

    $this->get(route('products.index'))->assertInertia(fn ($page) => $page
        ->where('seoTitle', null)
        ->where('title', 'All Products'));
});
