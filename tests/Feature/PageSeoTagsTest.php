<?php

declare(strict_types=1);

use App\Http\Controllers\ProductListingController;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FE-009: production has no SSR, so the first HTML response is all a share
 * preview or simple crawler ever sees. SSR is forced off to match it.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

test('a category uses its own search text when the admin filled it in', function () {
    $category = Category::factory()->create([
        'name' => 'Electronics', 'description' => 'Plain description', 'is_active' => true,
        'meta_title' => 'Best gadgets in Bangladesh', 'meta_description' => 'Phones and more',
    ]);

    $html = $this->get(route('category.show', $category->slug))->assertOk()->getContent();

    expect($html)->toContain('<title>Best gadgets in Bangladesh - '.config('app.name').'</title>')
        ->toContain('name="description" content="Phones and more"');
});

test('a category or sub-category with blank search text falls back to its name and description', function (Category|SubCategory $listing) {
    $html = $this->get(route('category.show', $listing->slug))->assertOk()->getContent();

    expect($html)->toContain('<title>Kitchen - '.config('app.name').'</title>')
        ->toContain('name="description" content="Pots and pans"')
        ->toContain('property="og:image" content="'.url('/images/brand/og-image.jpg').'"');
})->with([
    'category' => fn () => Category::factory()->create([
        'name' => 'Kitchen', 'description' => '<p>Pots and pans</p>', 'image' => '/placeholder.svg', 'is_active' => true,
    ]),
    'sub-category' => fn () => SubCategory::factory()->create([
        'name' => 'Kitchen', 'description' => '<p>Pots and pans</p>', 'image' => '/placeholder.svg', 'is_active' => true,
        'category_id' => Category::factory()->create(['is_active' => true])->id,
    ]),
]);

test('a blog post is shared as an article with its own picture', function () {
    $blog = Blog::factory()->create([
        'title' => 'Buying guide', 'meta_title' => null, 'meta_description' => null, 'excerpt' => 'How to choose',
        'featured_image' => '/storage/blogs/guide.webp', 'is_published' => true, 'published_at' => now()->subDay(),
    ]);

    $html = $this->get(route('blog.show', $blog->slug))->assertOk()->getContent();

    expect($html)->toContain('property="og:type" content="article"')
        ->toContain('property="og:title" content="Buying guide - '.config('app.name').'"')
        ->toContain('property="og:description" content="How to choose"')
        ->toContain('property="og:image" content="'.url('/storage/blogs/guide.webp').'"');
});

test('a brand page and a policy page carry their own tags', function () {
    $brand = Brand::factory()->create(['name' => 'Acme', 'description' => 'Acme tools', 'is_active' => true]);
    Page::updateOrCreate(['slug' => 'terms'], ['title' => 'Terms', 'content' => 'Text', 'meta_title' => null, 'meta_description' => 'Our terms', 'is_published' => true]);

    expect($this->get(route('brand.show', $brand->slug))->assertOk()->getContent())
        ->toContain('<title>Acme - '.config('app.name').'</title>')
        ->toContain('name="description" content="Acme tools"');

    expect($this->get(route('terms'))->assertOk()->getContent())
        ->toContain('<title>Terms - '.config('app.name').'</title>')
        ->toContain('name="description" content="Our terms"');
});

test('any other public page gets the site defaults, with a share picture that really exists', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('property="og:image" content="'.url('/images/brand/og-image.jpg').'"')
        ->toContain('name="description" content="'.e(PageSeo::DEFAULT_DESCRIPTION).'"')
        ->toContain('rel="canonical" href="'.url('/').'"')
        ->and(file_exists(public_path('images/brand/og-image.jpg')))->toBeTrue();
});

test('each main shop page has its own title, description and canonical in the first HTML', function () {
    $pages = [
        '/' => 'Online Shopping in Bangladesh',
        '/products' => ProductListingController::ALL_PRODUCTS_TITLE,
        '/brands' => 'All Brands',
        '/about' => 'About Us',
        '/contact' => 'Contact Us',
        '/flash-sales' => 'Flash Sale',
        '/bundle-deals' => 'Bundle Deals',
    ];
    $descriptions = [];

    foreach ($pages as $path => $title) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->toContain('<title>'.e($title.' - '.config('app.name')).'</title>')
            ->toContain('property="og:title" content="'.e($title.' - '.config('app.name')).'"')
            ->toContain('rel="canonical" href="'.url($path).'"');

        preg_match('/name="description" content="([^"]*)"/', $html, $tag);
        $descriptions[$path] = $tag[1];
    }

    expect(array_unique($descriptions))->toHaveCount(count($pages));
});

test('the about and contact descriptions follow the intro text the admin saved', function (string $key, string $path) {
    Setting::updateOrCreate(['key' => $key], ['value' => 'Our own <b>intro</b> text', 'group' => 'general']);

    expect($this->get($path)->assertOk()->getContent())->toContain('name="description" content="Our own intro text"');
})->with([
    'about' => ['about_hero_description', '/about'],
    'contact' => ['contact_page_hero_description', '/contact'],
]);

test('every public page asks for the large Twitter card once and leaves the rest to Open Graph', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    expect(substr_count($html, 'name="twitter:'))->toBe(1)
        ->and($html)->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('property="og:image" content="');
})->with(['/', '/products', '/about']);

test('long descriptions are cut to what a search result shows', function () {
    $category = Category::factory()->create(['description' => str_repeat('word ', 100), 'is_active' => true]);

    preg_match('/name="description" content="([^"]*)"/', $this->get(route('category.show', $category->slug))->getContent(), $tag);

    expect(mb_strlen($tag[1]))->toBeLessThanOrEqual(163);
});

test('filtered urls point at the plain page, and later pages keep their number', function (array $query, string $expected) {
    $category = Category::factory()->create(['slug' => 'kitchen', 'is_active' => true]);

    $html = $this->get(route('category.show', [$category->slug, ...$query]))->assertOk()->getContent();

    expect($html)->toContain('rel="canonical" href="'.url($expected).'"');
})->with([
    'sorted' => [['sort' => 'price_asc'], '/category/kitchen'],
    'first page' => [['page' => 1], '/category/kitchen'],
    'second page' => [['page' => 2, 'sort' => 'price_asc'], '/category/kitchen?page=2'],
]);

test('private pages tell search engines to stay away and name no canonical page', function (string $path) {
    $response = $this->get($path);

    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->getContent())->not->toContain('rel="canonical"');
})->with(['/cart', '/search?q=shoes', '/login', '/admin/login', '/wishlist', '/order/track']);

test('shop pages stay open to search engines', function (string $path) {
    $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag');
})->with(['/', '/products', '/brands', '/blog']);

test('admins can save search text on categories and sub-categories, within the column limit', function () {
    actingAs(createAdmin(), 'admin');
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    $search = ['meta_title' => 'Search title', 'meta_description' => 'Search description'];

    $this->put(route('admin.categories.update', $category), ['name' => $category->name, 'slug' => $category->slug, ...$search])
        ->assertSessionHasNoErrors();
    $this->put(route('admin.sub-categories.update', $subCategory), [
        'name' => $subCategory->name, 'slug' => $subCategory->slug, 'category_id' => $category->id, ...$search,
    ])->assertSessionHasNoErrors();

    expect($category->fresh())->meta_title->toBe('Search title')->meta_description->toBe('Search description')
        ->and($subCategory->fresh())->meta_title->toBe('Search title')->meta_description->toBe('Search description');

    $this->put(route('admin.categories.update', $category), [
        'name' => $category->name, 'slug' => $category->slug, 'meta_description' => str_repeat('x', 256),
    ])->assertSessionHasErrors('meta_description');
});

test('a saved title that already names the site is not given a second site name (DISC-95)', function (string $saved, string $printed) {
    Category::factory()->create(['name' => 'Watches', 'slug' => 'watches', 'meta_title' => $saved, 'is_active' => true]);

    $html = $this->get(route('category.show', 'watches'))->assertOk()->getContent();

    expect($html)->toContain('<title>'.e($printed).'</title>')
        ->toContain('property="og:title" content="'.e($printed).'"');
})->with([
    'ends with the site name' => [fn () => 'Watches | '.config('app.name'), fn () => 'Watches | '.config('app.name')],
    'site name in another case' => [fn () => 'Watches at '.mb_strtolower(config('app.name')), fn () => 'Watches at '.mb_strtolower(config('app.name'))],
    'no site name' => ['Premium watches', fn () => 'Premium watches - '.config('app.name')],
]);

test('the browser title rule matches the server one (DISC-95)', function () {
    expect(file_get_contents(resource_path('js/app.tsx')))
        ->toContain('title.toLowerCase().includes(appName.toLowerCase())');
});

test('a description written as a heading line over its text leaves the heading out (DISC-95)', function (Category|Brand $owner, string $url) {
    preg_match('/name="description" content="([^"]*)"/', $this->get($url)->assertOk()->getContent(), $tag);

    expect(html_entity_decode($tag[1]))->toBe('Lamps & lights for every room. Delivered across Bangladesh.');
})->with([
    'category' => fn () => [$c = Category::factory()->create(['slug' => 'lamps', 'is_active' => true, 'meta_description' => null,
        'description' => "Lamps in Bangladesh | Shop Online\r\n\r\nLamps &amp; lights for every room. Delivered across Bangladesh."]), route('category.show', 'lamps')],
    'brand' => fn () => [$b = Brand::factory()->create(['slug' => 'lumen', 'is_active' => true, 'meta_description' => null,
        'description' => '<h2>Lumen Lighting</h2><p>Lamps &amp; lights for every room.</p><p>Delivered across Bangladesh.</p>']), route('brand.show', 'lumen')],
]);

test('a saved search description is used as written, and a one-line description keeps its first words (DISC-95)', function () {
    Category::factory()->create(['slug' => 'toys', 'is_active' => true, 'description' => 'Toys for kids', 'meta_description' => 'Fun toys in Bangladesh']);
    Category::factory()->create(['slug' => 'games', 'is_active' => true, 'description' => 'Board games', 'meta_description' => null]);

    expect($this->get(route('category.show', 'toys'))->getContent())->toContain('name="description" content="Fun toys in Bangladesh"')
        ->and($this->get(route('category.show', 'games'))->getContent())->toContain('name="description" content="Board games"');
});

test('the same sentence pasted several times in a row is said once (DISC-95)', function () {
    Setting::updateOrCreate(['key' => 'contact_page_hero_description'], ['value' => str_repeat('Our team is ready to help. ', 4), 'group' => 'general']);

    preg_match('/name="description" content="([^"]*)"/', $this->get(route('contact'))->assertOk()->getContent(), $tag);

    expect($tag[1])->toBe('Our team is ready to help.');
});

function savedDescriptionShown(string $description): string
{
    Category::factory()->create(['slug' => 'long', 'is_active' => true, 'meta_description' => $description]);
    preg_match('/name="description" content="([^"]*)"/', test()->get(route('category.show', 'long'))->getContent(), $tag);

    return html_entity_decode($tag[1]);
}

test('a long description ends at the last whole sentence that fits (DISC-95)', function () {
    $sentences = 'Warm lamps for bedrooms and living rooms. Soft light that is easy on the eyes. Delivered anywhere in Bangladesh.';

    expect(savedDescriptionShown($sentences.' Order today and pay cash when the parcel arrives at your door.'))->toBe($sentences);
});

test('a long description without a sentence end is cut after a whole word (DISC-95)', function () {
    $shown = savedDescriptionShown(str_repeat('lamp ', 40));

    expect($shown)->toMatch('/^(lamp )+lamp…$/u')
        ->and(mb_strlen($shown))->toBeLessThanOrEqual(161);
});

test('cutting a long Bangla description keeps every letter whole (DISC-95)', function () {
    // ও (U+0993) ends in the same byte as an en dash; a byte-based trim would break it.
    $shown = savedDescriptionShown(str_repeat('ও ', 100));

    expect(mb_check_encoding($shown, 'UTF-8'))->toBeTrue()
        ->and($shown)->toMatch('/^(ও )+ও…$/u');
});
