<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-56: /llms.txt summarises the shop for AI agents from live data.
 */

function llmsTxt(): string
{
    $response = test()->get('/llms.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/plain; charset=utf-8');

    return (string) $response->getContent();
}

test('it names the shop and links its key pages', function () {
    Setting::create(['key' => 'site_name', 'value' => 'ShoppyDrive', 'group' => 'general']);

    $text = llmsTxt();

    expect($text)->toStartWith("# ShoppyDrive\n\n> ")
        ->toContain('- [All products]('.url('/products').')')
        ->toContain('- [About us]('.url('/about').')')
        ->toContain('- [Contact]('.url('/contact').')')
        ->toContain('- [Sitemap]('.url('/sitemap.xml').')');
});

test('it lists active categories and published pages only, each at its real URL', function () {
    Category::factory()->create(['name' => 'Kitchen', 'slug' => 'kitchen', 'is_active' => true]);
    Category::factory()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
    Page::create(['title' => 'Warranty', 'slug' => 'warranty', 'content' => '<p>W.</p>', 'is_published' => true]);
    Page::create(['title' => 'Draft', 'slug' => 'draft', 'content' => '<p>D.</p>', 'is_published' => false]);

    $text = llmsTxt();

    expect($text)->toContain('- [Kitchen]('.url('/category/kitchen').')')
        ->toContain('- [Warranty]('.url('/page/warranty').')')
        ->toContain('('.url('/terms').')')
        ->not->toContain('/category/hidden')
        ->not->toContain('/page/draft');
});

test('contact details appear only when saved', function () {
    expect(llmsTxt())->not->toContain('- Email:')->not->toContain('- Phone:');

    Setting::create(['key' => 'contact_email', 'value' => 'help@example.com', 'group' => 'contact']);
    Setting::create(['key' => 'contact_phone', 'value' => '+8801711000000', 'group' => 'contact']);

    expect(llmsTxt())->toContain("- Email: help@example.com\n- Phone: +8801711000000\n");
});

test('an admin-entered name cannot start a new Markdown line or break a link', function () {
    Setting::create(['key' => 'site_name', 'value' => "Shop\n## Injected", 'group' => 'general']);
    Category::factory()->create(['name' => 'Odd] (x', 'slug' => 'odd', 'is_active' => true]);

    $text = llmsTxt();

    expect($text)->toStartWith("# Shop ## Injected\n")
        ->not->toContain("\n## Injected")
        ->toContain('- [Odd) (x]('.url('/category/odd').')');
});
