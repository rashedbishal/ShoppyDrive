<?php

declare(strict_types=1);

use App\Models\Product;

/*
 * DISC-04: the product JSON-LD is printed inside <script type="application/ld+json">
 * in the Blade root view. A product name or meta description containing
 * </script> or HTML must never break out of that element, and the JSON that
 * crawlers decode must still carry exactly the original values.
 */

function disc04ProductPage(array $attributes): array
{
    // Same as production: no SSR bundle, so the Blade root view prints the tags.
    config(['inertia.ssr.enabled' => false]);

    $product = Product::factory()->create(array_merge([
        'price' => 1500,
        'stock_quantity' => 3,
        'is_active' => true,
        'published_at' => now()->subDay(),
    ], $attributes));

    $html = test()->get(route('product.show', $product->slug))->assertOk()->getContent();

    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match))->toBe(1);

    return [$html, $match[1], $product];
}

test('a product name and meta description with </script>, HTML, quotes and ampersands cannot break out of the JSON-LD script', function () {
    $name = 'Evil "Bottle" & Co\'s </script><script>alert(1)</script>';
    $description = '<img src=x onerror=alert(2)> "quoted" & \'single\' </SCRIPT><b>bold</b>';

    [$html, $jsonLd] = disc04ProductPage(['name' => $name, 'meta_description' => $description]);

    expect($jsonLd)->not->toContain('<')
        ->and($jsonLd)->not->toContain('>')
        ->and($jsonLd)->not->toContain("'")
        ->and($jsonLd)->not->toContain('&')
        ->and($html)->not->toContain('</script><script>alert(1)</script>')
        ->and($html)->not->toContain('<img src=x onerror=alert(2)>')
        ->and($html)->not->toContain('</SCRIPT><b>bold</b>');

    $decoded = json_decode($jsonLd, true, flags: JSON_THROW_ON_ERROR);
    expect($decoded['name'])->toBe($name)
        ->and($decoded['description'])->toBe($description);
});

test('Unicode in the name and description survives exactly, and line separators cannot end the script', function () {
    $name = "শার্ট Premium ✨ Tee \u{2028}line \u{2029}para";
    $description = 'বাংলা বর্ণনা — "smart quotes" ‘ok’ 😀';

    [, $jsonLd] = disc04ProductPage(['name' => $name, 'meta_description' => $description]);

    expect($jsonLd)->not->toContain("\u{2028}")
        ->and($jsonLd)->not->toContain("\u{2029}");

    $decoded = json_decode($jsonLd, true, flags: JSON_THROW_ON_ERROR);
    expect($decoded['name'])->toBe($name)
        ->and($decoded['description'])->toBe($description);
});

test('the existing Product JSON-LD fields are unchanged for an ordinary product', function () {
    [, $jsonLd, $product] = disc04ProductPage([
        'name' => 'Comfy T-Shirt',
        'meta_description' => null,
        'short_description' => 'A very comfy t-shirt.',
    ]);

    expect(json_decode($jsonLd, true, flags: JSON_THROW_ON_ERROR))->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Comfy T-Shirt',
        'description' => 'A very comfy t-shirt.',
        'image' => [],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'BDT',
            'price' => (string) $product->price,
            'availability' => 'https://schema.org/InStock',
        ],
    ])->and($jsonLd)->toContain('"@context":"https://schema.org"');
});
