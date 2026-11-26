<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Setting;
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
        'featured_image' => '/storage/blogs/rice.webp', 'author_name' => 'Nadia Rahman',
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
            'author' => ['@type' => 'Person', 'name' => 'Nadia Rahman'],
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

/** @return array<string, mixed>|null the post's author schema, or null when it has none */
function blogAuthorSchema(string $authorName): ?array
{
    $blog = Blog::factory()->create(['author_name' => $authorName, 'is_published' => true, 'published_at' => now()->subDay()]);

    return blogJsonLdBlocks(route('blog.show', $blog->slug))[0]['author'] ?? null;
}

test('an author with a personal name is a Person (DISC-85)', function (string $name) {
    expect(blogAuthorSchema($name))->toBe(['@type' => 'Person', 'name' => $name]);
})->with(['Nadia Rahman', 'A. K. M. Hasan', "Mary-Jane O'Neil", 'নাদিয়া রহমান']);

test('the shop writing as itself is the same Organization as the publisher, whatever the team is called', function (string $name) {
    Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'ShoppyDrive', 'group' => 'general']);

    expect(blogAuthorSchema($name))->toBe([
        '@type' => 'Organization', '@id' => url('/').'#organization', 'name' => 'ShoppyDrive', 'url' => url('/'),
    ]);
})->with(['ShoppyDrive Team', 'Team ShoppyDrive', 'shoppydrive editorial', 'ShoppyDrive']);

test('the saved legal business name also identifies the shop', function () {
    Setting::updateOrCreate(['key' => 'legal_business_name'], ['value' => 'Drive Retail Ltd.', 'group' => 'general']);

    expect(blogAuthorSchema('Drive Retail Ltd. Content Desk')['@id'] ?? null)->toBe(url('/').'#organization');
});

test('another team or company is an Organization under its own name', function (string $name) {
    expect(blogAuthorSchema($name))->toBe(['@type' => 'Organization', 'name' => $name]);
})->with(['Tech Review Team', 'Acme Ltd', 'Editorial Staff', 'Gadget Media Group']);

test('a bare role, a single word or anything not shaped like a name is left out instead of guessed', function (string $name) {
    expect(blogAuthorSchema($name))->toBeNull();
})->with(['Admin', 'Guest Writer', 'Nadia', 'Writer 2', 'nadia@example.com', 'The Very Long Author Name Here', 'Nadia </script>']);

test('an author name with HTML stays inside the JSON-LD script, escaped', function () {
    $blog = Blog::factory()->create(['author_name' => 'Acme <b>Labs</b> Ltd', 'is_published' => true, 'published_at' => now()->subDay()]);

    $html = $this->get(route('blog.show', $blog->slug))->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);

    expect($match[1])->not->toContain('<b>')
        ->and(json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['author'])->toBe(['@type' => 'Organization', 'name' => 'Acme <b>Labs</b> Ltd']);
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
