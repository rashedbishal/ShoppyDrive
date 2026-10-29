<?php

declare(strict_types=1);

use App\Models\Blog;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * These tests only ever exercise the command against this test run's
 * isolated in-memory sqlite database (see phpunit.xml: DB_CONNECTION=sqlite,
 * DB_DATABASE=:memory:) — never the real application database. "Legacy"
 * unsanitized rows are inserted via a raw `DB::table(...)->update()` call
 * that deliberately bypasses the Blog/Product/LandingPage models' own
 * Stage 1 sanitizing mutators, to simulate content that predates them;
 * that bypass is this test file's own fixture setup, not something the
 * command under test ever does itself.
 */
test('command refuses to run outside local/testing environments', function () {
    app()->instance('env', 'production');

    $this->artisan('content:sanitization-dry-run')
        ->assertExitCode(1)
        ->expectsOutputToContain('Refusing to run');
});

test('command runs read-only in the testing environment and reports unchanged legitimate content', function () {
    Blog::factory()->create(['content' => '<p>Already clean content.</p>']);

    $this->artisan('content:sanitization-dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('Blog.content')
        ->expectsOutputToContain('Unchanged')
        ->expectsOutputToContain('Would change');
});

test('command detects and reports unsafe HTML in legacy blog content without modifying it', function () {
    $blog = Blog::factory()->create(['content' => '<p>Safe</p>']);

    $maliciousHtml = '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)">';
    DB::table('blogs')->where('id', $blog->id)->update(['content' => $maliciousHtml]);

    $this->artisan('content:sanitization-dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('unsafe HTML removed')
        ->expectsOutputToContain($blog->slug);

    // The whole point of a dry run: the stored row must be byte-for-byte
    // untouched afterward.
    expect(DB::table('blogs')->where('id', $blog->id)->value('content'))->toBe($maliciousHtml);
});

test('command detects unsafe HTML in legacy product descriptions without modifying it', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'sub_category_id' => $subCategory->id,
        'description' => 'Safe description',
    ]);

    $maliciousHtml = '<p>Great</p><iframe src="https://evil.com"></iframe>';
    DB::table('products')->where('id', $product->id)->update(['description' => $maliciousHtml]);

    $this->artisan('content:sanitization-dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('unsafe HTML removed')
        ->expectsOutputToContain($product->slug);

    expect(DB::table('products')->where('id', $product->id)->value('description'))->toBe($maliciousHtml);
});

test('command detects unsafe HTML in legacy landing page custom_content while leaving sibling fields unreported and unmodified', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    $product = Product::factory()->create(['category_id' => $category->id, 'sub_category_id' => $subCategory->id]);

    $sections = LandingPage::defaultSections();
    $sections['hero']['headline'] = 'Original Headline';
    $sections['description']['custom_content'] = '<p>Safe</p>';

    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $product->id,
        'is_active' => true,
        'sections' => $sections,
    ]);

    $sections['description']['custom_content'] = '<p>Great</p><svg onload="alert(1)"></svg>';
    DB::table('landing_pages')->where('id', $page->id)->update(['sections' => json_encode($sections)]);

    $this->artisan('content:sanitization-dry-run')
        ->assertExitCode(0)
        ->expectsOutputToContain('LandingPage.sections.description.custom_content')
        ->expectsOutputToContain('unsafe HTML removed')
        ->expectsOutputToContain($page->slug);

    $storedSections = json_decode(DB::table('landing_pages')->where('id', $page->id)->value('sections'), true);

    expect($storedSections['description']['custom_content'])->toBe('<p>Great</p><svg onload="alert(1)"></svg>');
    expect($storedSections['hero']['headline'])->toBe('Original Headline');
});

test('command never calls a database-writing method', function () {
    $blog = Blog::factory()->create(['content' => '<script>alert(1)</script>']);
    DB::table('blogs')->where('id', $blog->id)->update(['content' => '<script>alert(1)</script>']);

    $countBefore = DB::table('blogs')->count();

    $this->artisan('content:sanitization-dry-run')->assertExitCode(0);

    expect(DB::table('blogs')->count())->toBe($countBefore);
    expect(DB::table('blogs')->where('id', $blog->id)->value('content'))->toBe('<script>alert(1)</script>');
});
