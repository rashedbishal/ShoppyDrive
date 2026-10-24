<?php

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('blog index only lists published posts', function () {
    $published = Blog::factory()->create(['title' => 'Published Post']);
    $draft = Blog::factory()->draft()->create(['title' => 'Draft Post']);

    $response = $this->get('/blog');

    $response->assertInertia(fn ($page) => $page
        ->component('blog/Index')
        ->has('blogs.data', 1)
        ->where('blogs.data.0.id', $published->id)
    );

    expect($draft)->not->toBeNull();
});

test('blog index excludes posts scheduled in the future', function () {
    Blog::factory()->create([
        'title' => 'Future Post',
        'published_at' => now()->addDay(),
    ]);

    $response = $this->get('/blog');

    $response->assertInertia(fn ($page) => $page->has('blogs.data', 0));
});

test('a published blog post can be viewed', function () {
    $blog = Blog::factory()->create(['title' => 'Hello World']);

    $response = $this->get("/blog/{$blog->slug}");

    $response->assertInertia(fn ($page) => $page
        ->component('blog/Show')
        ->where('blog.title', 'Hello World')
    );
});

test('an unpublished blog post returns 404', function () {
    $blog = Blog::factory()->draft()->create();

    $this->get("/blog/{$blog->slug}")->assertNotFound();
});
