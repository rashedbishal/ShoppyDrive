<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductMedia;

/*
 * FE-006: SSR isn't built by deploy, so Inertia's own client-side <Head>
 * never reaches curl, Facebook's scraper or search crawlers — the raw first
 * response must already carry real title/description/canonical/og/JSON-LD
 * tags, not the generic site title and an empty head.
 */
test('a product page response carries real SEO tags, not the generic site title', function () {
    // Matches production exactly: deploy builds no SSR bundle (npm run
    // build only), so Inertia's SSR dispatch always falls back to this
    // Blade view's own tags there. Forced off here too, since this
    // dev machine's own Vite dev server (if running) would otherwise make
    // Inertia SSR succeed through it and mask what this test checks.
    config(['inertia.ssr.enabled' => false]);

    $product = Product::factory()->create([
        'name' => 'Comfy T-Shirt',
        'meta_title' => null,
        'meta_description' => null,
        'short_description' => 'A very comfy t-shirt.',
        'price' => 1200,
        'stock_quantity' => 5,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);
    ProductMedia::create([
        'product_id' => $product->id,
        'path' => '/storage/products/comfy.jpg',
        'type' => 'image',
        'is_primary' => true,
        'sort_order' => 0,
    ]);

    $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

    expect($html)->toContain('<title>Comfy T-Shirt - '.config('app.name').'</title>');
    expect($html)->toContain('name="description" content="A very comfy t-shirt."');
    expect($html)->toContain('property="og:title" content="Comfy T-Shirt - '.config('app.name').'"');
    expect($html)->toContain('property="og:image" content="'.url('/storage/products/comfy.jpg').'"');
    expect($html)->toContain(route('product.show', $product->slug));
    expect($html)->toContain('"@type":"Product"');
    expect($html)->not->toContain('<title>Laravel</title>');
});

test('a product search title follows the same page - site format, and the page has one description', function () {
    config(['inertia.ssr.enabled' => false]);

    $product = Product::factory()->create([
        'name' => 'Comfy T-Shirt',
        'meta_title' => 'Cotton T-Shirt for Summer',
        'meta_description' => 'Breathable cotton.',
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

    expect($html)->toContain('<title>Cotton T-Shirt for Summer - '.config('app.name').'</title>')
        ->and(substr_count($html, 'name="description"'))->toBe(1)
        ->and($html)->toContain('name="description" content="Breathable cotton."');
});

test('the product page adds no second description after it loads in the browser (DISC-77)', function () {
    $source = (string) file_get_contents(resource_path('js/pages/catalog/ProductDetail.tsx'));

    expect($source)->not->toContain('name="description"')
        ->toContain('<Head title={product.meta_title || product.name} />');
});
