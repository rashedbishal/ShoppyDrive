<?php

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can create a blog post', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blogs.store'), [
        'title' => 'Shopping Tips',
        'slug' => 'shopping-tips',
        'excerpt' => 'A quick guide.',
        'content' => '<p>Body content.</p>',
        'author_name' => 'ShoppyDrive Team',
        'is_published' => true,
    ]);

    $response->assertRedirect(route('admin.blogs.index'));

    $blog = Blog::where('slug', 'shopping-tips')->first();
    expect($blog)->not->toBeNull();
    expect($blog->title)->toBe('Shopping Tips');
    expect($blog->is_published)->toBeTrue();
    expect($blog->published_at)->not->toBeNull();
});

test('blog title and content are required', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blogs.store'), [
        'slug' => 'missing-fields',
    ]);

    $response->assertInvalid(['title', 'content']);
});

test('blog slug must be unique', function () {
    actingAs($this->admin, 'admin');

    Blog::factory()->create(['slug' => 'existing-post']);

    $response = $this->post(route('admin.blogs.store'), [
        'title' => 'Another Post',
        'slug' => 'existing-post',
        'content' => '<p>Body.</p>',
    ]);

    $response->assertInvalid(['slug']);
});

test('admin can update a blog post', function () {
    actingAs($this->admin, 'admin');

    $blog = Blog::factory()->create(['title' => 'Old Title']);

    $response = $this->put(route('admin.blogs.update', $blog), [
        'title' => 'New Title',
        'slug' => $blog->slug,
        'content' => $blog->content,
        'is_published' => $blog->is_published,
    ]);

    $response->assertRedirect(route('admin.blogs.index'));
    expect($blog->refresh()->title)->toBe('New Title');
});

test('creating a blog post strips malicious HTML from content', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blogs.store'), [
        'title' => 'Shopping Tips',
        'slug' => 'shopping-tips',
        'content' => '<p>Great tips</p><script>alert(1)</script><svg onload="alert(1)"><script>alert(2)</script></svg><iframe src="https://evil.com"></iframe>',
    ]);

    $response->assertRedirect(route('admin.blogs.index'));

    $blog = Blog::where('slug', 'shopping-tips')->first();

    expect($blog->content)->not->toContain('<script');
    expect($blog->content)->not->toContain('<svg');
    expect($blog->content)->not->toContain('<iframe');
    expect($blog->content)->not->toContain('onload');
    expect($blog->content)->toContain('Great tips');
});

test('creating a blog post strips a javascript: link href while keeping the link text', function () {
    actingAs($this->admin, 'admin');

    $this->post(route('admin.blogs.store'), [
        'title' => 'Shopping Tips',
        'slug' => 'shopping-tips',
        'content' => '<a href="javascript:alert(1)">click me</a>',
    ]);

    $content = Blog::where('slug', 'shopping-tips')->first()->content;

    expect($content)->not->toContain('javascript:');
    expect($content)->toContain('click me');
});

test('updating a blog post preserves legitimate formatting in content', function () {
    actingAs($this->admin, 'admin');

    $blog = Blog::factory()->create();

    $this->put(route('admin.blogs.update', $blog), [
        'title' => $blog->title,
        'slug' => $blog->slug,
        'is_published' => $blog->is_published,
        'content' => '<h1>Big Sale</h1><p>Check our <b>new</b> <a href="https://example.com">collection</a>.</p><ul><li>Item one</li></ul>',
    ]);

    $content = $blog->refresh()->content;

    expect($content)
        ->toContain('<h1>Big Sale</h1>')
        ->toContain('<b>new</b>')
        ->toContain('href="https://example.com"')
        ->toContain('<ul><li>Item one</li></ul>');
});

test('a relative uploaded image URL in blog content survives sanitization', function () {
    actingAs($this->admin, 'admin');

    $this->post(route('admin.blogs.store'), [
        'title' => 'Shopping Tips',
        'slug' => 'shopping-tips',
        'content' => '<p>See below.</p><img src="/storage/content-images/sample.webp" alt="sample">',
    ]);

    expect(Blog::where('slug', 'shopping-tips')->first()->content)
        ->toContain('/storage/content-images/sample.webp');
});

test('admin can delete a blog post', function () {
    actingAs($this->admin, 'admin');

    $blog = Blog::factory()->create();

    $response = $this->delete(route('admin.blogs.destroy', $blog));

    $response->assertRedirect(route('admin.blogs.index'));
    expect(Blog::find($blog->id))->toBeNull();
});

test('guests cannot access the admin blog routes', function () {
    $blog = Blog::factory()->create();

    $this->get(route('admin.blogs.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.blogs.edit', $blog))->assertRedirect(route('admin.login'));
});
