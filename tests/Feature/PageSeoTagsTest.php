<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\SubCategory;
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
        ->toContain('property="og:image" content="'.url('/Shoppydrive.jpg').'"');
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

    expect($html)->toContain('property="og:image" content="'.url('/Shoppydrive.jpg').'"')
        ->toContain('name="description" content="Premium e-commerce')
        ->toContain('rel="canonical" href="'.url('/').'"')
        ->and(file_exists(public_path('Shoppydrive.jpg')))->toBeTrue();
});

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
