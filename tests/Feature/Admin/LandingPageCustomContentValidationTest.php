<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
    $this->product = Product::factory()->create(['is_active' => true]);

    // Non-empty placeholders for every string field: Laravel's
    // ConvertEmptyStringsToNull middleware turns '' into null on every real
    // HTTP round-trip, which would make the sibling-preservation assertions
    // below compare against a value that was never going to survive
    // regardless of this feature — irrelevant to what's being tested here.
    $sections = LandingPage::defaultSections();
    $sections['hero']['headline'] = 'Original Headline';
    $sections['hero']['subheadline'] = 'Original Subheadline';
    $sections['footer']['text'] = 'Original Footer Text';
    $sections['testimonials']['items'][0]['name'] = 'Original Customer';
    $sections['testimonials']['items'][0]['text'] = 'Original testimonial text';
    $sections['faq']['items'][0]['question'] = 'Original question';
    $sections['faq']['items'][0]['answer'] = 'Original answer';

    $this->page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);
});

test('sections.description.custom_content rejects an oversized value', function () {
    actingAs($this->admin, 'admin');

    $sections = $this->page->sections;
    $sections['description']['custom_content'] = str_repeat('a', 20001);

    $response = $this->put(route('admin.landing-pages.update', $this->page), [
        'title' => $this->page->title,
        'slug' => $this->page->slug,
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $response->assertSessionHasErrors(['sections.description.custom_content']);
});

test('sections.description.custom_content rejects a non-string value', function () {
    actingAs($this->admin, 'admin');

    $sections = $this->page->sections;
    $sections['description']['custom_content'] = ['not', 'a', 'string'];

    $response = $this->put(route('admin.landing-pages.update', $this->page), [
        'title' => $this->page->title,
        'slug' => $this->page->slug,
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $response->assertSessionHasErrors(['sections.description.custom_content']);
});

test('sections.description.custom_content accepts a value within bounds and preserves legitimate formatting', function () {
    actingAs($this->admin, 'admin');

    $sections = $this->page->sections;
    $sections['description']['custom_content'] = '<p>Great product details</p>';

    $response = $this->put(route('admin.landing-pages.update', $this->page), [
        'title' => $this->page->title,
        'slug' => $this->page->slug,
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $response->assertSessionDoesntHaveErrors();

    expect($this->page->refresh()->sections['description']['custom_content'])
        ->toBe('<p>Great product details</p>');
});

test('sections.description.custom_content strips malicious HTML', function () {
    actingAs($this->admin, 'admin');

    $sections = $this->page->sections;
    $sections['description']['custom_content'] = '<p>Details</p><script>alert(1)</script><img src="x" onerror="alert(1)">';

    $response = $this->put(route('admin.landing-pages.update', $this->page), [
        'title' => $this->page->title,
        'slug' => $this->page->slug,
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $response->assertSessionDoesntHaveErrors();

    $customContent = $this->page->refresh()->sections['description']['custom_content'];

    expect($customContent)->not->toContain('<script');
    expect($customContent)->not->toContain('onerror');
    expect($customContent)->toContain('Details');
});

test('saving a new custom_content value leaves every sibling section field byte-for-byte unchanged', function () {
    actingAs($this->admin, 'admin');

    $original = $this->page->sections;

    $sections = $original;
    $sections['description']['custom_content'] = 'Updated description copy';

    $response = $this->put(route('admin.landing-pages.update', $this->page), [
        'title' => $this->page->title,
        'slug' => $this->page->slug,
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $response->assertSessionDoesntHaveErrors();

    $saved = $this->page->refresh()->sections;

    // Sanitization is active now, so plain text gets auto-wrapped in a <p>
    // (AutoFormat.AutoParagraph) — that's expected; the point of this test
    // is that nothing else in the blob changes, asserted below.
    expect($saved['description']['custom_content'])->toBe('<p>Updated description copy</p>');

    // Everything else in the same JSON blob must be untouched.
    expect($saved['hero'])->toBe($original['hero']);
    expect($saved['benefits'])->toBe($original['benefits']);
    expect($saved['description']['enabled'])->toBe($original['description']['enabled']);
    expect($saved['description']['title'])->toBe($original['description']['title']);
    expect($saved['description']['use_product_description'])->toBe($original['description']['use_product_description']);
    expect($saved['gallery'])->toBe($original['gallery']);
    expect($saved['testimonials'])->toBe($original['testimonials']);
    expect($saved['faq'])->toBe($original['faq']);
    expect($saved['footer'])->toBe($original['footer']);
});
