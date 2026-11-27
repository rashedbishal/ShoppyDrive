<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\ImageOptimizer;
use App\Support\ImageVariants;
use App\Support\PreloadImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * The image-performance project: the main image is preloaded from the HTML
 * (IMG-F2/F3), video hero slides can carry a poster (IMG-F3), the header
 * logo gets small copies (IMG-F6), existing images can be given copies by
 * images:variants (IMG-F5) and the storefront components use all of it
 * (IMG-F5/F7/F8). Production has no SSR, so SSR is off here too.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
    Storage::fake('public');
    Storage::fake('local');
});

function perfImage(string $directory, int $width = 1200, int $height = 900, string $name = 'photo.jpg'): string
{
    return '/storage/'.app(ImageOptimizer::class)->store(UploadedFile::fake()->image($name, $width, $height), $directory);
}

/** @return list<string> every <link rel="preload" as="image"> tag in the page */
function imagePreloads(string $url): array
{
    preg_match_all('#<link rel="preload" as="image"[^>]*>#', test()->get($url)->assertOk()->getContent(), $matches);

    return $matches[0];
}

function perfProduct(): Product
{
    $category = Category::factory()->create(['is_active' => true]);

    return Product::factory()->create(['category_id' => $category->id, 'sub_category_id' => null, 'brand_id' => null, 'is_active' => true, 'published_at' => now()->subDay()]);
}

function showHeroSlider(): void
{
    HomepageSetting::updateOrCreate(['section_key' => 'hero_slider'], ['section_name' => 'Hero', 'is_visible' => true, 'sort_order' => 1]);
}

// ── IMG-F2: product page ────────────────────────────────────────────────────

test('a product page preloads the photo its gallery opens on, with the same srcset and sizes as the <img>', function () {
    $product = perfProduct();
    ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'is_primary' => false, 'sort_order' => 0]);
    $primary = ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'is_primary' => true, 'sort_order' => 1]);

    $preloads = imagePreloads(route('product.show', $product->slug));

    expect($preloads)->toHaveCount(1)
        ->and($preloads[0])->toContain('href="'.$primary->path.'"')
        ->toContain('imagesrcset="'.e($primary->srcset).'"')
        ->toContain('imagesizes="'.PreloadImage::PRODUCT_GALLERY_SIZES.'"')
        ->toContain('fetchpriority="high"');
});

test('a product with a default variant preloads the photo the gallery really opens on: that variant\'s own, else its value\'s', function () {
    $product = perfProduct();
    $red = AttributeValue::create(['attribute_id' => Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR])->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    $default = ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true, 'attribute_value_ids' => [$red->id]]);
    ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);
    $valueImage = ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'attribute_value_id' => $red->id, 'sort_order' => 1]);

    expect(imagePreloads(route('product.show', $product->slug))[0])->toContain('href="'.$valueImage->path.'"');

    $own = ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'variant_id' => $default->id, 'sort_order' => 2]);

    expect(imagePreloads(route('product.show', $product->slug))[0])->toContain('href="'.$own->path.'"');
});

test('variants with no default flagged (legacy) preload nothing rather than guess', function () {
    $product = perfProduct();
    ProductVariant::factory()->count(2)->create(['product_id' => $product->id, 'is_default' => false]);
    ProductMedia::create(['product_id' => $product->id, 'path' => perfImage('products'), 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);

    expect(imagePreloads(route('product.show', $product->slug)))->toBe([]);
});

test('the gallery starts on the same photo the server preloads (IMG-F2)', function () {
    $page = (string) file_get_contents(resource_path('js/pages/catalog/ProductDetail.tsx'));

    expect($page)->toContain('openingMediaIndex(product.media ?? [], product.variants ?? [])')
        ->toContain('ProductController::galleryOpeningMedia()');
});

test('a product photo without copies is preloaded by its plain URL only', function () {
    $product = perfProduct();
    Storage::disk('public')->put('products/legacy.png', 'x');
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/products/legacy.png', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);

    $preloads = imagePreloads(route('product.show', $product->slug));

    expect($preloads)->toHaveCount(1)
        ->and($preloads[0])->toContain('href="/storage/products/legacy.png"')->not->toContain('imagesrcset');
});

test('nothing is preloaded for a product with no media or one that opens on a video', function () {
    $empty = perfProduct();
    $video = perfProduct();
    ProductMedia::create(['product_id' => $video->id, 'path' => '/storage/products/clip.mp4', 'type' => 'video', 'is_primary' => true, 'sort_order' => 0]);

    expect(imagePreloads(route('product.show', $empty->slug)))->toBe([])
        ->and(imagePreloads(route('product.show', $video->slug)))->toBe([]);
});

test('a preload URL is escaped in the HTML', function () {
    $product = perfProduct();
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/products/a"><script>x</script>.jpg', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);

    $html = $this->get(route('product.show', $product->slug))->getContent();

    expect($html)->not->toContain('"><script>x</script>')
        ->toContain('href="/storage/products/a&quot;&gt;&lt;script&gt;x&lt;/script&gt;.jpg"');
});

// ── IMG-F3: homepage hero ───────────────────────────────────────────────────

test('the homepage preloads the first hero slide\'s image', function () {
    showHeroSlider();
    $first = HeroSlide::create(['title' => 'First', 'image' => perfImage('hero-slides', 2400, 1000), 'is_active' => true, 'sort_order' => 1]);
    HeroSlide::create(['title' => 'Second', 'image' => perfImage('hero-slides', 2400, 1000), 'is_active' => true, 'sort_order' => 2]);

    $preloads = imagePreloads(route('home'));

    expect($preloads)->toHaveCount(1)
        ->and($preloads[0])->toContain('href="'.$first->image.'"')
        ->toContain('imagesizes="'.PreloadImage::HERO_SIZES.'"');
});

test('a video first slide preloads its poster, and without a poster nothing is preloaded', function () {
    showHeroSlider();
    $slide = HeroSlide::create(['title' => 'Video', 'image' => '/storage/hero-slides/clip.mp4', 'poster' => perfImage('hero-slides', 1280, 720), 'is_active' => true, 'sort_order' => 1]);

    expect(imagePreloads(route('home')))->toHaveCount(1)
        ->and(imagePreloads(route('home'))[0])->toContain('href="'.$slide->poster.'"');

    $slide->update(['poster' => null]);

    expect(imagePreloads(route('home')))->toBe([]);
});

test('a hidden hero slider preloads nothing', function () {
    HomepageSetting::updateOrCreate(['section_key' => 'hero_slider'], ['section_name' => 'Hero', 'is_visible' => false, 'sort_order' => 1]);
    HeroSlide::create(['title' => 'First', 'image' => perfImage('hero-slides', 2400, 1000), 'is_active' => true, 'sort_order' => 1]);

    expect(imagePreloads(route('home')))->toBe([]);
});

test('a hero moved below another section is not preloaded', function () {
    showHeroSlider();
    HomepageSetting::updateOrCreate(['section_key' => 'featured_products'], ['section_name' => 'Featured', 'is_visible' => true, 'sort_order' => 0]);
    HeroSlide::create(['title' => 'First', 'image' => perfImage('hero-slides', 2400, 1000), 'is_active' => true, 'sort_order' => 1]);

    expect(imagePreloads(route('home')))->toBe([]);
});

test('the poster column is nullable and existing slides keep working without one', function () {
    expect(Schema::hasColumn('hero_slides', 'poster'))->toBeTrue();

    $slide = HeroSlide::create(['title' => 'Old', 'image' => '/storage/hero-slides/clip.mp4', 'is_active' => true, 'sort_order' => 1]);

    expect($slide->fresh()->poster)->toBeNull()
        ->and($slide->fresh()->toArray()['poster_srcset'])->toBeNull();
});

/** @return array<string, mixed> */
function heroForm(array $overrides = []): array
{
    return [
        'title' => 'Hero', 'eyebrow' => '', 'description' => '', 'primary_cta_label' => '', 'primary_cta_href' => '',
        'is_active' => '1', 'sort_order' => '1', ...$overrides,
    ];
}

test('an admin can add a poster to a slide; it is stored as optimised WebP with copies', function () {
    actingAs(createAdmin(), 'admin');

    // A fake MP4 fails the real-video check (WebVideo), so the slide here is a still image.
    $this->post(route('admin.hero-slides.store'), heroForm([
        'image' => UploadedFile::fake()->image('slide.jpg', 1920, 1080),
        'poster' => UploadedFile::fake()->image('still.jpg', 1280, 720),
    ]));
    $slide = HeroSlide::sole();

    expect($slide->poster)->toStartWith('/storage/hero-slides/')->toEndWith('.webp')
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath(substr($slide->poster, 9), 800)))->toBeTrue();
})->skip(fn () => ! function_exists('imagewebp'), 'GD without WebP');

test('a poster can be replaced, kept as it is, or removed', function () {
    actingAs(createAdmin(), 'admin');
    $oldPoster = perfImage('hero-slides', 1280, 720);
    $slide = HeroSlide::create(['title' => 'Video', 'image' => '/storage/hero-slides/clip.mp4', 'poster' => $oldPoster, 'is_active' => true, 'sort_order' => 1]);

    $this->post(route('admin.hero-slides.update', $slide), heroForm(['_method' => 'PUT', 'poster' => $oldPoster]))->assertSessionHasNoErrors();
    expect($slide->fresh()->poster)->toBe($oldPoster);

    $this->post(route('admin.hero-slides.update', $slide), heroForm(['_method' => 'PUT', 'poster' => UploadedFile::fake()->image('new.jpg', 1280, 720)]))->assertSessionHasNoErrors();
    $newPoster = $slide->fresh()->poster;
    expect($newPoster)->not->toBe($oldPoster)
        ->and(Storage::disk('public')->exists(substr($oldPoster, 9)))->toBeFalse()
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath(substr($oldPoster, 9), 400)))->toBeFalse();

    $this->post(route('admin.hero-slides.update', $slide), heroForm(['_method' => 'PUT', 'poster' => '']))->assertSessionHasNoErrors();
    expect($slide->fresh()->poster)->toBeNull()
        ->and(Storage::disk('public')->exists(substr($newPoster, 9)))->toBeFalse();
});

test('a poster must be a still image of at most 5 MB', function (UploadedFile $poster) {
    actingAs(createAdmin(), 'admin');
    $slide = HeroSlide::create(['title' => 'Video', 'image' => '/storage/hero-slides/clip.mp4', 'is_active' => true, 'sort_order' => 1]);

    $this->post(route('admin.hero-slides.update', $slide), heroForm(['_method' => 'PUT', 'poster' => $poster]))
        ->assertSessionHasErrors('poster');

    expect($slide->fresh()->poster)->toBeNull();
})->with([
    'a video' => fn () => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
    'a PDF' => fn () => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
    'too big' => fn () => UploadedFile::fake()->image('big.jpg', 100, 100)->size(6000),
]);

// ── IMG-F6: logo ────────────────────────────────────────────────────────────

test('an uploaded logo keeps its PNG for emails and gets small WebP copies for the header', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), ['logo' => UploadedFile::fake()->image('logo.png', 658, 576)])->assertSessionHasNoErrors();
    $logo = (string) Setting::where('key', 'logo')->value('value');

    expect($logo)->toEndWith('.png')
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath(substr($logo, 9), 160)))->toBeTrue();

    auth('admin')->logout();
    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('settings.logo_srcset', fn ($srcset) => str_contains((string) $srcset, '-160w.webp 1x')));
});

test('replacing the logo removes the old one\'s copies', function () {
    actingAs(createAdmin(), 'admin');
    $this->put(route('admin.settings.update'), ['logo' => UploadedFile::fake()->image('logo.png', 658, 576)]);
    $old = substr((string) Setting::where('key', 'logo')->value('value'), 9);

    $this->put(route('admin.settings.update'), ['logo' => UploadedFile::fake()->image('new-logo.png', 658, 576)]);

    expect(Storage::disk('public')->exists($old))->toBeFalse()
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath($old, 160)))->toBeFalse();
});

// ── IMG-F5: images:variants backfill ────────────────────────────────────────

/** An image stored the old way: original only, no copies. */
function legacyImage(string $path, int $width = 1200, int $height = 900): string
{
    $file = UploadedFile::fake()->image('legacy.jpg', $width, $height);
    Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

    return $path;
}

test('images:variants only reports unless told to apply, and never touches originals', function () {
    $path = legacyImage('products/legacy.jpg');
    $before = Storage::disk('public')->get($path);

    $this->artisan('images:variants')->expectsOutputToContain('1 image(s) need smaller copies.')->assertSuccessful();
    expect(Storage::disk('public')->allFiles('products'))->toBe([$path]);

    $this->artisan('images:variants --apply')->expectsOutputToContain('Made 3 cop(ies) for 1 of 1 image(s)')->assertSuccessful();

    expect(Storage::disk('public')->exists(ImageVariants::variantPath($path, 800)))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->toBe($before);
});

test('images:variants is safe to re-run, can be limited, and skips other folders', function () {
    legacyImage('products/a.jpg');
    legacyImage('categories/b.jpg');
    legacyImage('avatars/c.jpg');

    $this->artisan('images:variants --apply --limit=1')->expectsOutputToContain('1 image(s) left')->assertSuccessful();
    $this->artisan('images:variants --apply')->assertSuccessful();
    $this->artisan('images:variants')->expectsOutputToContain('0 image(s) need smaller copies.')->assertSuccessful();

    expect(collect(Storage::disk('public')->allFiles('avatars'))->filter(ImageVariants::isVariant(...)))->toBeEmpty();
});

test('an image GD cannot decode is reported once as kept at one size, not left over forever', function () {
    $file = UploadedFile::fake()->image('cut.png', 1200, 900);
    Storage::disk('public')->put('products/cut.png', substr((string) file_get_contents($file->getRealPath()), 0, 200));

    $this->artisan('images:variants --apply')
        ->expectsOutputToContain('1 image(s) could not be copied')
        ->doesntExpectOutputToContain('left: run it again')
        ->assertSuccessful();

    expect(Storage::disk('public')->allFiles('products'))->toBe(['products/cut.png']);
});

test('images:variants --prune deletes only copies whose original is gone, and only with --apply', function () {
    legacyImage('products/kept.jpg');
    $this->artisan('images:variants --apply')->assertSuccessful();
    Storage::disk('public')->put('products/gone-400w.webp', 'x');

    $this->artisan('images:variants --prune')->expectsOutputToContain('Would delete 1 cop(ies)')->assertSuccessful();
    expect(Storage::disk('public')->exists('products/gone-400w.webp'))->toBeTrue();

    $this->artisan('images:variants --apply --prune')->expectsOutputToContain('Deleted 1 cop(ies)')->assertSuccessful();
    expect(Storage::disk('public')->exists('products/gone-400w.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists(ImageVariants::variantPath('products/kept.jpg', 400)))->toBeTrue();
});

// ── Frontend wiring (no SSR, so checked in the source) ──────────────────────

test('the frontend uses the same sizes the server preloads with', function () {
    $sizes = (string) file_get_contents(resource_path('js/lib/image-sizes.ts'));

    // Prettier may wrap a long value onto the next line.
    expect($sizes)->toMatch('/productGallery:\s*'.preg_quote("'".PreloadImage::PRODUCT_GALLERY_SIZES."'", '/').'/')
        ->toMatch('/fullWidth:\s*'.preg_quote("'".PreloadImage::HERO_SIZES."'", '/').'/');
});

test('product cards fetch the hover photo only where it can be shown (IMG-F7)', function () {
    $card = (string) file_get_contents(resource_path('js/components/shared/ProductCard.tsx'));

    expect($card)->toContain('const canHover = useCanHover();')
        ->toContain("const secondaryUrl = canHover ? (secondaryImage?.path ?? '') : '';")
        ->toContain('srcSet={primaryImage?.srcset ?? undefined}');
});

test('the product gallery opens on its photo at once, at high priority, with lazy thumbnails (IMG-F2)', function () {
    $page = (string) file_get_contents(resource_path('js/pages/catalog/ProductDetail.tsx'));

    expect($page)->toMatch('/<AnimatePresence\s+mode="wait"\s+initial=\{false\}\s*>/')
        ->toContain('fetchPriority="high"')
        ->and(substr_count($page, 'IMAGE_SIZES.thumbnail'))->toBe(2);
});

test('a video hero slide shows its poster (IMG-F3)', function () {
    $home = (string) file_get_contents(resource_path('js/pages/Home.tsx'));

    expect($home)->toContain('poster={activeSlide.poster ?? undefined}')
        ->toContain('srcSet={activeSlide.imageSrcset ?? undefined}');
});
