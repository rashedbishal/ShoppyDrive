<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Inertia\Testing\AssertableInertia;

/*
 * DISC-100: after a tap, iOS/iPadOS Safari sends emulated mouse events; a
 * mouseenter handler that changes visible content makes Safari spend that
 * first tap on "hover", so a link needed a second tap (the list-view card's
 * hover image, the header's nav pill, the mega menu's category panel), and
 * the header's hover-to-open made a tap on "All Categories" open the menu
 * and close it again in the same tap.
 *
 * Covered here: the source rule (hover handlers are pointer handlers that
 * run for a mouse only) and the server side of card navigation. Real-browser
 * evidence was gathered separately with headless Chrome (touch emulation:
 * one tap on "All Categories" now leaves it open); Safari's tap rule itself
 * can only be confirmed on an iPhone/iPad.
 */

/** @return array<string, string> */
function touchHoverSources(): array
{
    $files = [
        'helper' => 'js/lib/mouse-hover.ts',
        'product card' => 'js/components/shared/ProductCard.tsx',
        'header' => 'js/components/layout/Header.tsx',
        'mega menu' => 'js/components/layout/MegaMenu.tsx',
    ];

    return array_map(fn (string $path): string => (string) file_get_contents(resource_path($path)), $files);
}

test('hover effects on product cards, header nav and the mega menu run for a mouse only, never on a tap', function () {
    $sources = touchHoverSources();

    expect($sources['helper'])->toContain("return event.pointerType === 'mouse';");

    foreach (['product card', 'header', 'mega menu'] as $component) {
        expect($sources[$component])
            ->not->toContain('onMouseEnter')
            ->not->toContain('onMouseLeave')
            ->toContain("import { isMousePointer } from '@/lib/mouse-hover';")
            ->toMatch('/if\s*\(\s*isMousePointer\(\s*event,?\s*\)\s*\)\s*\{/');
    }

    // Every pointer hover handler in those components checks the pointer type.
    foreach (['product card', 'header', 'mega menu'] as $component) {
        preg_match_all('/onPointer(?:Enter|Leave)=\{\(\s*event,?\s*\)\s*=>\s*\{\s*if\s*\(\s*isMousePointer\(\s*event,?\s*\)\s*\)/', $sources[$component], $guarded);
        expect(substr_count($sources[$component], 'onPointerEnter=') + substr_count($sources[$component], 'onPointerLeave='))
            ->toBe(count($guarded[0]));
    }
});

test('on touch, "All Categories" is opened and closed by its own button click alone', function () {
    $header = touchHoverSources()['header'];

    expect($header)->toContain('setIsMegaMenuOpen((prev) => !prev)')
        // the hover-open sits behind the mouse check
        ->and((bool) preg_match("/if \\(isMousePointer\\(event\\)\\) \\{\\s*setHoveredNavKey\\('categories'\\);\\s*openMegaMenu\\(\\);/", $header))->toBeTrue();
});

test('every product card link in a category, sub-category and brand listing opens that product', function (string $listing) {
    $category = Category::factory()->create(['slug' => 'gifts-cute-accessories', 'name' => 'Gifts & Cute Accessories', 'is_active' => true]);
    $subCategory = SubCategory::factory()->create(['slug' => 'bubu-dudu-items', 'category_id' => $category->id, 'is_active' => true]);
    $brand = Brand::factory()->create(['slug' => 'tomi-brand', 'is_active' => true]);

    foreach (['Bubu Dudu Cute Plush Toy – Soft Teddy Couple Gift Set', 'Press & Twist Decompression Ball', 'TOMI T-106 Face Gear Watch'] as $name) {
        Product::factory()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'is_active' => true,
            'published_at' => now()->subDay(),
            'stock_quantity' => 3,
        ]);
    }

    $url = match ($listing) {
        'category' => route('category.show', 'gifts-cute-accessories'),
        'sub-category' => route('category.show', 'bubu-dudu-items'),
        'brand' => route('brand.show', 'tomi-brand'),
    };

    $slugs = [];
    $this->get($url)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$slugs) {
        $slugs = collect($page->toArray()['props']['products']['data'])->pluck('slug')->all();
    });

    expect($slugs)->toHaveCount(3);

    foreach ($slugs as $slug) {
        expect($slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
        $this->get(route('product.show', $slug))->assertOk();
    }
})->with(['category', 'sub-category', 'brand']);
