<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-63: production has no SSR, so agents that don't run JavaScript read
 * only the first HTML response. The <noscript> fallback gives them one <h1>,
 * the footer's About/Contact/policy links and saved contact details.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

function noscriptBlock(string $html): string
{
    expect(preg_match('#<noscript>\s*<header>(.*?)</noscript>#s', $html, $match))->toBe(1);

    return $match[1];
}

test('the homepage has exactly one h1 naming the page and the site, plus about, contact and legal links', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    $fallback = noscriptBlock($html);

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($fallback)->toContain('<h1>Online Shopping in Bangladesh - '.config('app.name').'</h1>')
        ->toContain('href="'.url('/about').'"')
        ->toContain('href="'.url('/contact').'"')
        ->toContain('href="'.url('/terms').'"')
        ->toContain('href="'.url('/privacy-policy').'"');
});

test('saved contact details become tel: and mailto: links, unsaved ones are left out', function () {
    expect(noscriptBlock($this->get(route('home'))->getContent()))->not->toContain('tel:')->not->toContain('mailto:');

    Setting::create(['key' => 'contact_phone', 'value' => '+880 1711-000000', 'group' => 'contact']);
    Setting::create(['key' => 'contact_email', 'value' => 'help@example.com', 'group' => 'contact']);

    expect(noscriptBlock($this->get(route('home'))->getContent()))
        ->toContain('<a href="tel:+8801711000000">+880 1711-000000</a>')
        ->toContain('<a href="mailto:help@example.com">help@example.com</a>');
});

test('another page names itself in the h1, escaped', function () {
    $product = Product::factory()->create([
        'name' => 'Kettle <script>alert(1)</script>', 'is_active' => true, 'published_at' => now()->subDay(),
    ]);

    $fallback = noscriptBlock($this->get(route('product.show', $product->slug))->assertOk()->getContent());

    expect($fallback)->toContain('<h1>Kettle &lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

test('admin pages get no fallback', function () {
    expect($this->get('/admin/login')->getContent())->not->toContain('<nav aria-label="Site">');
});

test('the homepage component carries one visible h1 with the intro heading (DISC-90)', function () {
    $source = (string) file_get_contents(resource_path('js/pages/Home.tsx'));

    preg_match('#<h1 className="([^"]*)">\s*\{intro\.heading\}\s*</h1>#', $source, $h1);

    expect(substr_count($source, '<h1'))->toBe(1)
        ->and($h1)->not->toBeEmpty()
        ->and($h1[1])->not->toContain('sr-only')->not->toContain('hidden');
});
