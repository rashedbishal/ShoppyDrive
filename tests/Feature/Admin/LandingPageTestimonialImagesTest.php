<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = createAdmin();
    $this->page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo', 'product_id' => Product::factory()->create(['is_active' => true])->id,
        'is_active' => true, 'sections' => LandingPage::defaultSections(),
    ]);
});

function saveTestimonials(array $testimonials)
{
    $sections = LandingPage::defaultSections();
    $sections['testimonials'] = array_merge($sections['testimonials'], $testimonials);

    return actingAs(test()->admin, 'admin')->put(route('admin.landing-pages.update', test()->page), [
        'title' => 'Promo', 'slug' => 'promo', 'product_id' => test()->page->product_id, 'is_active' => true, 'sections' => $sections,
    ]);
}

test('a testimonial image is stored and its path returned', function () {
    $path = actingAs($this->admin, 'admin')
        ->postJson(route('admin.landing-pages.testimonial-images.store', $this->page), [
            'image' => UploadedFile::fake()->image('screenshot.png', 1170, 2532),
        ])->assertOk()->json('path');

    expect($path)->toStartWith('/storage/landing-testimonials/');
    Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));
});

test('only jpg, png and webp images up to 100 KB are accepted as testimonial images', function () {
    $post = fn (UploadedFile $file) => actingAs($this->admin, 'admin')
        ->postJson(route('admin.landing-pages.testimonial-images.store', $this->page), ['image' => $file]);

    $post(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))->assertUnprocessable();
    $post(UploadedFile::fake()->image('anim.gif'))->assertUnprocessable();
    $post(UploadedFile::fake()->image('big.jpg')->size(101))->assertUnprocessable();
    $post(UploadedFile::fake()->image('small.jpg')->size(100))->assertOk();
    $post(UploadedFile::fake()->image('ok.webp'))->assertOk();
});

test('the testimonial mode and images are saved and reach the public page', function () {
    saveTestimonials([
        'enabled' => true, 'mode' => 'image',
        'images' => ['/storage/landing-testimonials/a.webp', '/storage/landing-testimonials/b.webp'],
    ])->assertSessionHasNoErrors();

    auth('admin')->logout();

    get(route('lp.show', 'promo'))->assertInertia(fn (Assert $page) => $page
        ->where('page.sections.testimonials.mode', 'image')
        ->where('page.sections.testimonials.images', ['/storage/landing-testimonials/a.webp', '/storage/landing-testimonials/b.webp']));
});

test('testimonials stay in text mode by default', function () {
    expect($this->page->getSections()['testimonials'])->toMatchArray(['mode' => 'text', 'images' => []]);
});

test('a testimonial image path outside the testimonial folder, or an unknown mode, is refused', function () {
    saveTestimonials(['mode' => 'image', 'images' => ['/storage/products/x.jpg']])
        ->assertSessionHasErrors('sections.testimonials.images.0');

    saveTestimonials(['mode' => 'video'])->assertSessionHasErrors('sections.testimonials.mode');

    saveTestimonials(['mode' => 'image', 'images' => array_fill(0, 13, '/storage/landing-testimonials/a.webp')])
        ->assertSessionHasErrors('sections.testimonials.images');
});
