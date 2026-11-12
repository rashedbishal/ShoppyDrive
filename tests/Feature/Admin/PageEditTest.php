<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Page;
use Inertia\Testing\AssertableInertia as Assert;
use Mews\Purifier\Facades\Purifier;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/*
 * DISC-05: Admin › Pages edits (never creates or deletes) the six legal/info
 * pages. Content is sanitized on save with the dedicated `pages` purifier
 * profile, which keeps the FAQ's H2 (group) / H3 (question) structure.
 */

afterEach(function () {
    @unlink(GenerateSitemapCommand::path());
});

function disc05Page(string $slug): Page
{
    // The legal pages are inserted by migrations (seed_legal_pages,
    // publish_accurate_policy_pages), so every test database already has them.
    return Page::where('slug', $slug)->firstOrFail();
}

/** What a reader sees: tags removed, entities decoded (&amp; and & render the same), whitespace collapsed. */
function disc05VisibleText(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/** Storefront checks run as a visitor: a signed-in admin is redirected to /admin (RedirectAdminToDashboard). */
function disc05AsVisitor(): void
{
    auth('admin')->logout();
}

function disc05Payload(Page $page, array $overrides = []): array
{
    return array_merge([
        'title' => $page->title,
        'content' => $page->content,
        'meta_title' => $page->meta_title,
        'meta_description' => $page->meta_description,
        'is_published' => true,
    ], $overrides);
}

test('an admin without the pages permission cannot list, open or save pages', function () {
    $page = disc05Page('terms');
    actingAs(createAdmin(['permissions' => ['blog' => ['view', 'create', 'update']]]), 'admin');

    $this->get(route('admin.pages.index'))->assertForbidden();
    $this->get(route('admin.pages.edit', $page))->assertForbidden();
    $this->put(route('admin.pages.update', $page), disc05Payload($page, ['title' => 'Hijacked']))->assertForbidden();

    expect($page->fresh()->title)->not->toBe('Hijacked');
});

test('a view-only admin can list pages but not edit them', function () {
    $page = disc05Page('terms');
    actingAs(createAdmin(['permissions' => ['pages' => ['view']]]), 'admin');

    $this->get(route('admin.pages.index'))->assertOk();
    $this->get(route('admin.pages.edit', $page))->assertForbidden();
    $this->put(route('admin.pages.update', $page), disc05Payload($page))->assertForbidden();
});

test('the list shows exactly the six editable pages, and a non-editable page is a 404 for the editor', function () {
    $other = Page::create(['title' => 'Other', 'slug' => 'some-other-page', 'content' => '<p>x</p>', 'is_published' => true]);
    actingAs(createAdmin(['permissions' => ['pages' => ['view', 'update']]]), 'admin');

    $this->get(route('admin.pages.index'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/pages/index')
            ->has('pages', 6)
            ->where('pages.0.slug', 'privacy-policy'));

    $this->get(route('admin.pages.edit', $other))->assertNotFound();
    $this->put(route('admin.pages.update', $other), disc05Payload($other, ['title' => 'Changed']))->assertNotFound();
    expect($other->fresh()->title)->toBe('Other');
});

test('an editor saves title, content and SEO fields; scripts and event handlers are stripped', function () {
    $page = disc05Page('shipping-policy');
    actingAs(createAdmin(['permissions' => ['pages' => ['view', 'update']]]), 'admin');

    $this->put(route('admin.pages.update', $page), disc05Payload($page, [
        'title' => 'Shipping Policy (updated)',
        'content' => '<h2 onclick="x()">Delivery</h2><script>alert(1)</script><p>Within <strong>3 days</strong>.</p><a href="javascript:alert(2)">bad</a><img src=x onerror=alert(3)>',
        'meta_title' => 'Shipping',
        'meta_description' => 'How we ship.',
    ]))->assertRedirect(route('admin.pages.index'));

    $page->refresh();
    expect($page->title)->toBe('Shipping Policy (updated)')
        ->and($page->meta_title)->toBe('Shipping')
        ->and($page->meta_description)->toBe('How we ship.')
        ->and($page->content)->toContain('<h2>Delivery</h2>')
        ->and($page->content)->toContain('<strong>3 days</strong>')
        ->and($page->content)->not->toContain('<script')
        ->and($page->content)->not->toContain('onclick')
        ->and($page->content)->not->toContain('onerror')
        ->and($page->content)->not->toContain('javascript:');
});

test('saving the FAQ unchanged keeps its H2 groups, H3 questions and answers', function () {
    $faq = disc05Page('faq');
    $before = $faq->content;
    actingAs(createAdmin(['permissions' => ['pages' => ['view', 'update']]]), 'admin');

    $this->put(route('admin.pages.update', $faq), disc05Payload($faq))->assertRedirect();

    $after = $faq->fresh()->content;
    foreach (['<h2', '<h3', '<p', '<a ', '<strong'] as $tag) {
        expect(substr_count($after, $tag))->toBe(substr_count($before, $tag), "count of {$tag}");
    }
    expect(substr_count($after, '<h3'))->toBeGreaterThan(0)
        ->and(disc05VisibleText($after))->toBe(disc05VisibleText($before));
});

test('the pages profile keeps every seeded page\'s structure and links, while the default profile is unchanged', function () {
    foreach (Page::ADMIN_EDITABLE_SLUGS as $slug) {
        $html = (string) file_get_contents(database_path("seeders/pages/{$slug}.html"));
        $clean = Purifier::clean($html, 'pages');

        foreach (['<h2', '<h3', '<p', '<ul', '<li', '<a ', '<strong', '<em', 'target="_blank"'] as $marker) {
            expect(substr_count($clean, $marker))->toBe(substr_count($html, $marker), "{$slug}: {$marker}");
        }
    }

    expect(Purifier::clean('<h3>Q</h3>'))->not->toContain('<h3>');
});

test('unpublishing a page makes its URL 404 and drops it from the sitemap; republishing restores both', function () {
    $page = disc05Page('refund-policy');
    actingAs(createAdmin(['permissions' => ['pages' => ['view', 'update']]]), 'admin');

    $admin = createAdmin(['permissions' => ['pages' => ['view', 'update']]]);
    $this->put(route('admin.pages.update', $page), disc05Payload($page, ['is_published' => false]))->assertRedirect();

    disc05AsVisitor();
    $this->get('/refund-policy')->assertNotFound();
    $this->artisan('sitemap:generate')->assertSuccessful();
    expect((string) file_get_contents(GenerateSitemapCommand::path()))->not->toContain('/refund-policy<');

    actingAs($admin, 'admin');
    $this->put(route('admin.pages.update', $page), disc05Payload($page, ['is_published' => true]))->assertRedirect();

    disc05AsVisitor();
    $this->get('/refund-policy')->assertOk()->assertInertia(fn (Assert $p) => $p->component('static/Page'));
    $this->artisan('sitemap:generate')->assertSuccessful();
    expect((string) file_get_contents(GenerateSitemapCommand::path()))->toContain('/refund-policy<');
});

test('the storefront URL serves the saved content and SEO title, and the save is audited', function () {
    $page = disc05Page('terms');
    actingAs(createAdmin(['permissions' => ['pages' => ['view', 'update']]]), 'admin');

    $this->put(route('admin.pages.update', $page), disc05Payload($page, [
        'title' => 'Terms of Service',
        'meta_title' => 'Our Terms',
        'content' => '<h2>Section</h2><p>Updated wording.</p>',
    ]))->assertRedirect();

    disc05AsVisitor();
    $html = $this->get('/terms')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('static/Page')
            ->where('page.content', fn (string $content): bool => str_contains($content, '<h2>Section</h2>') && str_contains($content, '<p>Updated wording.</p>')))
        ->getContent();
    expect($html)->toContain('<title>Our Terms - ');

    expect(Activity::where('subject_type', Page::class)->where('subject_id', $page->id)->where('event', 'updated')->exists())->toBeTrue();
});
