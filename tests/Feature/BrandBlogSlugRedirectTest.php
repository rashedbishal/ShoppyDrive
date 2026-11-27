<?php

declare(strict_types=1);

use App\Console\Commands\GenerateSitemapCommand;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SlugRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-92 (SEO audit SEO-29 / F-08, completing DISC-91): a renamed brand or
 * blog post's old URL 301s to its current one, in one hop, only while it may
 * be shown. Both are permanently deleted (no soft deletes), so their rows go
 * with a plain delete. SSR is off, as in production.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

afterEach(function () {
    @unlink(GenerateSitemapCommand::path());
});

function redirectBrand(string $slug, array $attributes = []): Brand
{
    return Brand::factory()->create(['name' => 'Acme', 'slug' => $slug, 'is_active' => true, ...$attributes]);
}

function redirectPost(string $slug, array $attributes = []): Blog
{
    return Blog::factory()->create(['title' => 'Rice cooker guide', 'slug' => $slug, 'is_published' => true, 'published_at' => now()->subDay(), ...$attributes]);
}

// ── Basic redirects ─────────────────────────────────────────────────────────

test('a renamed brand or blog post\'s old URL 301s to its current URL, which loads', function () {
    redirectBrand('acme')->update(['slug' => 'acme-tools']);
    redirectPost('rice-guide')->update(['slug' => 'rice-cooker-guide']);

    $this->get('/brand/acme')->assertStatus(301)->assertRedirect(route('brand.show', 'acme-tools'));
    $this->get('/blog/rice-guide')->assertStatus(301)->assertRedirect(route('blog.show', 'rice-cooker-guide'));
    $this->get(route('brand.show', 'acme-tools'))->assertOk();
    $this->get(route('blog.show', 'rice-cooker-guide'))->assertOk();
});

test('the query string goes along with the redirect', function () {
    redirectBrand('acme')->update(['slug' => 'acme-tools']);
    redirectPost('rice-guide')->update(['slug' => 'rice-cooker-guide']);

    $this->get('/brand/acme?page=2')->assertRedirect(route('brand.show', 'acme-tools').'?page=2');
    $this->get('/blog/rice-guide?utm_source=fb')->assertRedirect(route('blog.show', 'rice-cooker-guide').'?utm_source=fb');
});

// ── Chains, loops, collisions ───────────────────────────────────────────────

test('a → b → c sends a and b straight to c, and a → b → a serves a with no loop', function () {
    $brand = redirectBrand('a-brand');
    $brand->update(['slug' => 'b-brand']);
    $brand->update(['slug' => 'c-brand']);
    $post = redirectPost('a-post');
    $post->update(['slug' => 'b-post']);
    $post->update(['slug' => 'a-post']);

    $this->get('/brand/a-brand')->assertRedirect(route('brand.show', 'c-brand'));
    $this->get('/brand/b-brand')->assertRedirect(route('brand.show', 'c-brand'));
    $this->get('/blog/a-post')->assertOk();
    $this->get('/blog/b-post')->assertRedirect(route('blog.show', 'a-post'));
    expect(SlugRedirect::where('namespace', 'blog')->where('old_slug', 'a-post')->exists())->toBeFalse();
});

test('a live page wins, and an old slug moves to the newest record that gives it up', function () {
    redirectBrand('acme')->update(['slug' => 'acme-tools']);
    $newcomer = redirectBrand('acme', ['name' => 'Acme Kitchen']);

    $this->get('/brand/acme')->assertOk()->assertInertia(fn ($page) => $page->where('brand.id', $newcomer->id));

    $newcomer->update(['slug' => 'acme-kitchen']);
    expect(SlugRedirect::where('namespace', 'brand')->where('old_slug', 'acme')->count())->toBe(1);
    $this->get('/brand/acme')->assertRedirect(route('brand.show', 'acme-kitchen'));
});

test('the same old slug redirects separately for a product, a brand and a blog post', function () {
    redirectBrand('steel')->update(['slug' => 'steel-brand']);
    redirectPost('steel')->update(['slug' => 'steel-guide']);
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->create([
        'slug' => 'steel', 'category_id' => $category->id, 'sub_category_id' => null, 'brand_id' => null,
        'is_active' => true, 'published_at' => now()->subDay(),
    ])->update(['slug' => 'steel-pan']);

    expect(SlugRedirect::where('old_slug', 'steel')->pluck('namespace')->sort()->values()->all())->toBe(['blog', 'brand', 'product']);
    $this->get('/brand/steel')->assertRedirect(route('brand.show', 'steel-brand'));
    $this->get('/blog/steel')->assertRedirect(route('blog.show', 'steel-guide'));
    $this->get('/product/steel')->assertRedirect(route('product.show', 'steel-pan'));
});

// ── Hidden and deleted targets ──────────────────────────────────────────────

test('no redirect to an inactive brand or an unpublished or scheduled post', function (string $hide) {
    $brand = redirectBrand('acme');
    $brand->update(['slug' => 'acme-tools']);
    $post = redirectPost('rice-guide');
    $post->update(['slug' => 'rice-cooker-guide']);

    match ($hide) {
        'brand inactive' => $brand->update(['is_active' => false]),
        'post unpublished' => $post->update(['is_published' => false]),
        'post scheduled' => $post->update(['published_at' => now()->addDay()]),
    };

    $url = str_starts_with($hide, 'brand') ? '/brand/acme' : '/blog/rice-guide';
    $this->get($url)->assertNotFound();
})->with(['brand inactive', 'post unpublished', 'post scheduled']);

test('deleting a brand or blog post removes its redirects at once', function () {
    $brand = redirectBrand('acme');
    $brand->update(['slug' => 'acme-tools']);
    $post = redirectPost('rice-guide');
    $post->update(['slug' => 'rice-cooker-guide']);
    expect(SlugRedirect::count())->toBe(2);

    $brand->delete();
    $post->delete();

    expect(SlugRedirect::count())->toBe(0);
    $this->get('/brand/acme')->assertNotFound();
    $this->get('/blog/rice-guide')->assertNotFound();
});

test('a brand still used by a product still can\'t be deleted from the admin, and keeps its redirect', function () {
    actingAs(createAdmin(), 'admin');
    $brand = redirectBrand('acme');
    $brand->update(['slug' => 'acme-tools']);
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $category->id, 'sub_category_id' => null]);

    $this->delete(route('admin.brands.destroy', $brand));

    expect(Brand::find($brand->id))->not->toBeNull()
        ->and(SlugRedirect::where('old_slug', 'acme')->exists())->toBeTrue();
});

test('unknown brand and blog slugs are still 404', function () {
    $this->get('/brand/never-existed')->assertNotFound();
    $this->get('/blog/never-existed')->assertNotFound();
});

// ── Admin path and SEO ──────────────────────────────────────────────────────

test('a rename through the admin blog form is recorded after the form normalises the slug', function () {
    actingAs(createAdmin(), 'admin');
    $post = redirectPost('rice-guide');

    $this->put(route('admin.blogs.update', $post), [
        'title' => $post->title, 'slug' => 'Rice Cooker Guide', 'content' => '<p>Text</p>', 'is_published' => true,
    ])->assertSessionHasNoErrors();

    expect($post->fresh()->slug)->toBe('rice-cooker-guide');
    auth('admin')->logout();
    $this->get('/blog/rice-guide')->assertRedirect(route('blog.show', 'rice-cooker-guide'));
});

test('destinations keep their canonical, title and BlogPosting URL, and the sitemap lists only current URLs', function () {
    redirectBrand('acme', ['meta_title' => 'Acme Tools in Bangladesh'])->update(['slug' => 'acme-tools']);
    redirectPost('rice-guide')->update(['slug' => 'rice-cooker-guide']);

    expect($this->get(route('brand.show', 'acme-tools'))->getContent())
        ->toContain('rel="canonical" href="'.route('brand.show', 'acme-tools').'"')
        ->toContain('<title>Acme Tools in Bangladesh - '.config('app.name').'</title>');

    $html = $this->get(route('blog.show', 'rice-cooker-guide'))->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $jsonLd);
    expect($html)->toContain('rel="canonical" href="'.route('blog.show', 'rice-cooker-guide').'"')
        ->and(json_decode($jsonLd[1], true)['url'])->toBe(route('blog.show', 'rice-cooker-guide'));

    $this->artisan('sitemap:generate')->assertSuccessful();
    $locs = array_map('strval', (new SimpleXMLElement((string) file_get_contents(GenerateSitemapCommand::path())))->xpath('//*[local-name()="loc"]'));

    expect($locs)->toContain(route('brand.show', 'acme-tools'))
        ->toContain(route('blog.show', 'rice-cooker-guide'))
        ->not->toContain(route('brand.show', 'acme'))
        ->not->toContain(route('blog.show', 'rice-guide'));
});
