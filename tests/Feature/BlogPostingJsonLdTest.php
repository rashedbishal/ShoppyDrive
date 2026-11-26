<?php

declare(strict_types=1);

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * DISC-84: a published blog post carries BlogPosting JSON-LD built only from
 * what the post really has. Production has no SSR, so SSR is forced off.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

/** @return list<array<string, mixed>> every JSON-LD block on the page, decoded */
function blogJsonLdBlocks(string $url): array
{
    $html = test()->get($url)->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

test('a published post describes itself with its own headline, dates, picture, author and the shop as publisher', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $blog = Blog::factory()->create([
        'title' => 'How to choose a rice cooker', 'slug' => 'rice-cooker-guide',
        'excerpt' => 'Sizes, features and prices.', 'meta_description' => null,
        'featured_image' => '/storage/blogs/rice.webp', 'author_name' => 'Nadia',
        'is_published' => true, 'published_at' => '2026-11-18 09:30:00',
    ]);
    Carbon::setTestNow();

    $blocks = blogJsonLdBlocks(route('blog.show', 'rice-cooker-guide'));

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0])->toBe([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => 'How to choose a rice cooker',
            'description' => 'Sizes, features and prices.',
            'url' => route('blog.show', 'rice-cooker-guide'),
            'mainEntityOfPage' => route('blog.show', 'rice-cooker-guide'),
            'image' => [url('/storage/blogs/rice.webp')],
            'datePublished' => $blog->published_at->toIso8601String(),
            'dateModified' => $blog->updated_at->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => 'Nadia'],
            'publisher' => ['@type' => 'Organization', '@id' => url('/').'#organization', 'name' => config('app.name'), 'url' => url('/')],
        ]);
});

test('a post without a picture, author, excerpt or publish date leaves those out instead of inventing them', function () {
    $blog = Blog::factory()->create([
        'title' => 'Short note', 'excerpt' => null, 'meta_description' => null,
        'featured_image' => null, 'author_name' => null, 'is_published' => true, 'published_at' => null,
    ]);

    $post = blogJsonLdBlocks(route('blog.show', $blog->slug))[0];

    expect($post['@type'])->toBe('BlogPosting')
        ->and($post)->not->toHaveKey('image')->not->toHaveKey('author')
        ->not->toHaveKey('description')->not->toHaveKey('datePublished')
        ->toHaveKey('dateModified');
});

test('a headline with </script> or HTML cannot break out of the JSON-LD script', function () {
    $blog = Blog::factory()->create(['title' => 'Deals </script><script>alert(1)</script>', 'is_published' => true, 'published_at' => now()->subDay()]);

    $html = $this->get(route('blog.show', $blog->slug))->assertOk()->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(blogJsonLdBlocks(route('blog.show', $blog->slug))[0]['headline'])->toBe('Deals </script><script>alert(1)</script>');
});

test('drafts and scheduled posts stay hidden, and the blog index is not a BlogPosting', function () {
    $draft = Blog::factory()->create(['is_published' => false]);
    $scheduled = Blog::factory()->create(['is_published' => true, 'published_at' => now()->addDay()]);

    $this->get(route('blog.show', $draft->slug))->assertNotFound();
    $this->get(route('blog.show', $scheduled->slug))->assertNotFound();
    expect(array_column(blogJsonLdBlocks(route('blog.index')), '@type'))->not->toContain('BlogPosting');
});
