<?php

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can update a hero slide and upload a new image in the same request', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $slide = HeroSlide::create([
        'title' => 'Original Title',
        'image' => '/placeholder.svg',
        'is_active' => true,
        'sort_order' => 1,
        'stats' => [
            ['label' => 'Style drops', 'value' => 'Weekly'],
            ['label' => 'Returns', 'value' => 'Easy exchange'],
            ['label' => 'Delivery', 'value' => '24–48h'],
        ],
    ]);

    // Mirrors exactly what the browser now sends: a real POST with _method=PUT
    // spoofing (required because PHP never parses $_FILES/$_POST for a literal
    // PUT request with a multipart/form-data body).
    $response = $this->post(route('admin.hero-slides.update', $slide), [
        '_method' => 'PUT',
        'title' => 'Updated Title',
        'eyebrow' => '',
        'description' => '',
        'image' => UploadedFile::fake()->image('new-hero.jpg', 1920, 1080),
        'primary_cta_label' => '',
        'primary_cta_href' => '',
        'is_active' => '1',
        'sort_order' => '1',
        'stats' => [
            ['label' => 'Style drops', 'value' => 'Weekly'],
            ['label' => 'Returns', 'value' => 'Easy exchange'],
            ['label' => 'Delivery', 'value' => '24–48h'],
        ],
    ]);

    $response->assertRedirect(route('admin.hero-slides.index'));
    $response->assertSessionDoesntHaveErrors();

    $slide->refresh();

    expect($slide->title)->toBe('Updated Title');
    expect($slide->image)->not->toBe('/placeholder.svg');
    expect($slide->stats)->toBe([
        ['label' => 'Style drops', 'value' => 'Weekly'],
        ['label' => 'Returns', 'value' => 'Easy exchange'],
        ['label' => 'Delivery', 'value' => '24–48h'],
    ]);
});

test('admin can create a hero slide with a video upload', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.hero-slides.store'), [
        'title' => 'Video Slide',
        'eyebrow' => '',
        'description' => '',
        'image' => UploadedFile::fake()->create('clip.mp4', 10 * 1024, 'video/mp4'),
        'primary_cta_label' => '',
        'primary_cta_href' => '',
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertRedirect(route('admin.hero-slides.index'));
    $response->assertSessionDoesntHaveErrors();

    $slide = HeroSlide::where('title', 'Video Slide')->first();
    expect($slide)->not->toBeNull();
    expect($slide->image)->toEndWith('.mp4');
});

test('creating a hero slide rejects fields past their character limits', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.hero-slides.store'), [
        'title' => str_repeat('a', 101),
        'eyebrow' => str_repeat('a', 51),
        'description' => str_repeat('a', 201),
        'image' => UploadedFile::fake()->image('hero.jpg', 1920, 1080),
        'primary_cta_label' => str_repeat('a', 41),
        'primary_cta_href' => '/category/x',
        'is_active' => '1',
        'sort_order' => '1',
        'stats' => [
            ['label' => str_repeat('a', 31), 'value' => str_repeat('a', 31)],
        ],
    ]);

    $response->assertInvalid([
        'title',
        'eyebrow',
        'description',
        'primary_cta_label',
        'stats.0.label',
        'stats.0.value',
    ]);
});

test('uploading a hero slide file larger than 15MB is rejected', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.hero-slides.store'), [
        'title' => 'Too Big Slide',
        'eyebrow' => '',
        'description' => '',
        'image' => UploadedFile::fake()->create('too-big.mp4', 16 * 1024, 'video/mp4'),
        'primary_cta_label' => '',
        'primary_cta_href' => '',
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertInvalid(['image']);
    expect(HeroSlide::where('title', 'Too Big Slide')->exists())->toBeFalse();
});
