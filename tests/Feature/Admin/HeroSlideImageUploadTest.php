<?php

use App\Models\HeroSlide;
use App\Rules\WebVideo;
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
        'image' => fakeMp4Upload(),
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

/** @return array<string, mixed> */
function heroVideoSlidePayload(UploadedFile $video): array
{
    return [
        'title' => 'Video Slide',
        'image' => $video,
        'is_active' => '1',
        'sort_order' => '1',
    ];
}

test('a hero slide video is stored with its index moved in front of the video data', function () {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.hero-slides.store'), heroVideoSlidePayload(fakeMp4Upload(indexFirst: false)))
        ->assertSessionHasNoErrors();

    $stored = Storage::disk('public')->get(substr(HeroSlide::sole()->image, strlen('/storage/')));
    expect(strpos($stored, 'moov'))->toBeLessThan(strpos($stored, 'mdat'));
});

test('a hero slide accepts a 720p video in either orientation', function (int $width, int $height) {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.hero-slides.store'), heroVideoSlidePayload(fakeMp4Upload(width: $width, height: $height)))
        ->assertSessionHasNoErrors();

    expect(HeroSlide::where('title', 'Video Slide')->exists())->toBeTrue();
})->with(['landscape' => [1280, 720], 'upright' => [720, 1280]]);

test('a hero slide rejects a video that would slow the homepage, saying how to fix it', function (Closure $video, string $message) {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.hero-slides.store'), heroVideoSlidePayload($video()))
        ->assertSessionHasErrors(['image' => $message]);

    expect(HeroSlide::where('title', 'Video Slide')->exists())->toBeFalse();
})->with([
    'over 2 MB' => [fn () => fakeMp4Upload(totalBytes: 2 * 1024 * 1024 + 1), 'Videos must be 2 MB or smaller. '.WebVideo::HOW_TO_FIX.' If it is still too big, shorten the clip.'],
    '1080p' => [fn () => fakeMp4Upload(width: 1920, height: 1080), 'This video is 1920×1080 pixels. Videos must be 720p or smaller (at most 1280×720, or 720×1280 upright). '.WebVideo::HOW_TO_FIX],
    'WebM over 2 MB' => [fn () => UploadedFile::fake()->create('clip.webm', 2 * 1024 + 1, 'video/webm'), 'Videos must be 2 MB or smaller. '.WebVideo::HOW_TO_FIX.' If it is still too big, shorten the clip.'],
]);

test('a hero slide accepts a small WebM, which only the size limit applies to', function () {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.hero-slides.store'), heroVideoSlidePayload(UploadedFile::fake()->create('clip.webm', 500, 'video/webm')))
        ->assertSessionHasNoErrors();

    expect(HeroSlide::where('title', 'Video Slide')->exists())->toBeTrue();
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

test('creating a hero slide rejects more than 3 stats', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.hero-slides.store'), [
        'title' => 'Too Many Stats',
        'eyebrow' => '',
        'description' => '',
        'image' => UploadedFile::fake()->image('hero.jpg', 1920, 1080),
        'primary_cta_label' => '',
        'primary_cta_href' => '',
        'is_active' => '1',
        'sort_order' => '1',
        'stats' => [
            ['label' => 'One', 'value' => '1'],
            ['label' => 'Two', 'value' => '2'],
            ['label' => 'Three', 'value' => '3'],
            ['label' => 'Four', 'value' => '4'],
        ],
    ]);

    $response->assertInvalid(['stats']);
    expect(HeroSlide::where('title', 'Too Many Stats')->exists())->toBeFalse();
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
