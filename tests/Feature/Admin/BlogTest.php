<?php

use App\Models\Admin;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
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
