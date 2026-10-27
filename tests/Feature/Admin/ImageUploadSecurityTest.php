<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Models\SubCategory;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

function imageSecurityAdmin(): Admin
{
    return Admin::create(['name' => 'Admin', 'email' => 'image-security-'.uniqid().'@example.com', 'password' => bcrypt('x')]);
}

// A real, valid SVG document — the point of these tests is that content
// sniffing (not the filename) is what rejects it, so the name alternates
// between an honest `.svg` and a disguised raster extension below.
function maliciousSvgContent(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)"><script>alert(1)</script></svg>';
}

/**
 * UploadedFile::fake()->createWithContent() returns an
 * Illuminate\Http\Testing\File, whose getMimeType() is deliberately
 * overridden to guess from the *filename extension* — a testing
 * convenience, not representative of a real upload. To actually prove
 * content-sniffing (the thing the fix relies on) rejects a disguised
 * file, this constructs a real Illuminate\Http\UploadedFile pointing at
 * an actual temp file containing the given bytes — its getMimeType()
 * is NOT overridden, so it sniffs the real content via finfo exactly
 * like a genuine upload would, regardless of $originalName's extension
 * or the $declaredMime a malicious client would send as Content-Type.
 */
function realUploadedFileWithContent(string $originalName, string $content, string $declaredMime = 'application/octet-stream'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload-security-test');
    file_put_contents($path, $content);

    return new UploadedFile($path, $originalName, $declaredMime, null, true);
}

// ── Brands ───────────────────────────────────────────────────────────────────

test('a brand logo upload accepts a valid JPEG', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Acme', 'slug' => 'acme',
        'logo' => UploadedFile::fake()->image('logo.jpg'),
    ]);

    $response->assertRedirect(route('admin.brands.index'));
    $brand = Brand::where('slug', 'acme')->firstOrFail();
    expect($brand->logo)->toStartWith('/storage/brands/');
});

test('a brand logo upload of exactly 1MB is accepted', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Acme', 'slug' => 'acme',
        'logo' => UploadedFile::fake()->image('logo.png')->size(1024),
    ]);

    $response->assertRedirect(route('admin.brands.index'));
    expect(Brand::where('slug', 'acme')->exists())->toBeTrue();
});

test('a brand logo upload larger than 1MB is rejected', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Acme', 'slug' => 'acme',
        'logo' => UploadedFile::fake()->image('logo.png')->size(1025),
    ]);

    $response->assertSessionHasErrors(['logo' => 'The logo must be 1MB or smaller.']);
    expect(Brand::where('slug', 'acme')->exists())->toBeFalse();
});

test('editing a brand without a new logo keeps the stored path valid', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');
    $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme', 'logo' => '/storage/brands/'.str_repeat('a', 1100).'.png']);

    $response = $this->put(route('admin.brands.update', $brand), [
        'name' => 'Acme', 'slug' => 'acme', 'logo' => $brand->logo,
    ]);

    $response->assertSessionHasNoErrors();
});

test('a brand logo upload rejects an honest SVG file', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Evil Co', 'slug' => 'evil-co',
        'logo' => realUploadedFileWithContent('logo.svg', maliciousSvgContent(), 'image/svg+xml'),
    ]);

    $response->assertSessionHasErrors('logo');
    expect(Brand::where('slug', 'evil-co')->exists())->toBeFalse();
});

test('a brand logo upload rejects an SVG disguised with a .jpg extension', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Evil Co 2', 'slug' => 'evil-co-2',
        'logo' => realUploadedFileWithContent('logo.jpg', maliciousSvgContent(), 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('logo');
    expect(Brand::where('slug', 'evil-co-2')->exists())->toBeFalse();
});

test('a brand logo upload rejects an unrelated file type (e.g. a PHP script)', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.brands.store'), [
        'name' => 'Evil Co 3', 'slug' => 'evil-co-3',
        'logo' => realUploadedFileWithContent('shell.jpg', '<?php system($_GET["c"]); ?>', 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('logo');
    expect(Brand::where('slug', 'evil-co-3')->exists())->toBeFalse();
});

test('updating a brand without a new logo keeps the existing logo string untouched', function () {
    $admin = imageSecurityAdmin();
    $brand = Brand::factory()->create(['logo' => '/storage/brands/existing.jpg']);
    actingAs($admin, 'admin');

    $response = $this->put(route('admin.brands.update', $brand), [
        'name' => $brand->name, 'slug' => $brand->slug,
        'logo' => $brand->logo,
    ]);

    $response->assertRedirect(route('admin.brands.index'));
    expect($brand->refresh()->logo)->toBe('/storage/brands/existing.jpg');
});

// ── Categories ───────────────────────────────────────────────────────────────

test('a category image upload accepts a valid PNG', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Gadgets', 'slug' => 'gadgets',
        'image' => UploadedFile::fake()->image('cat.png'),
    ]);

    $response->assertRedirect(route('admin.categories.index'));
    expect(Category::where('slug', 'gadgets')->firstOrFail()->image)->toStartWith('/storage/categories/');
});

test('a category image upload rejects an SVG disguised with a .png extension', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Evil Cat', 'slug' => 'evil-cat',
        'image' => realUploadedFileWithContent('cat.png', maliciousSvgContent(), 'image/png'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(Category::where('slug', 'evil-cat')->exists())->toBeFalse();
});

// ── Sub-categories ───────────────────────────────────────────────────────────

test('a sub-category image upload accepts a valid WebP', function () {
    $admin = imageSecurityAdmin();
    $category = Category::factory()->create();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.sub-categories.store'), [
        'name' => 'Phones', 'slug' => 'phones', 'category_id' => $category->id,
        'image' => UploadedFile::fake()->image('phones.webp'),
    ]);

    $response->assertRedirect(route('admin.sub-categories.index'));
    expect(SubCategory::where('slug', 'phones')->firstOrFail()->image)->toStartWith('/storage/sub-categories/');
});

test('a sub-category image upload rejects a raw SVG file', function () {
    $admin = imageSecurityAdmin();
    $category = Category::factory()->create();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.sub-categories.store'), [
        'name' => 'Evil Sub', 'slug' => 'evil-sub', 'category_id' => $category->id,
        'image' => realUploadedFileWithContent('sub.svg', maliciousSvgContent(), 'image/svg+xml'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(SubCategory::where('slug', 'evil-sub')->exists())->toBeFalse();
});

// ── Promotional banners ──────────────────────────────────────────────────────

test('a promotional banner image upload accepts a valid JPEG', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.promotional-banners.store'), [
        'title' => 'Sale', 'is_active' => true, 'sort_order' => 1,
        'image' => UploadedFile::fake()->image('banner.jpg'),
    ]);

    $response->assertRedirect(route('admin.promotional-banners.index'));
    expect(PromotionalBanner::where('title', 'Sale')->firstOrFail()->image)->toStartWith('/storage/promotional-banners/');
});

test('a promotional banner image upload rejects an SVG disguised with a .jpg extension', function () {
    $admin = imageSecurityAdmin();
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.promotional-banners.store'), [
        'title' => 'Evil Sale', 'is_active' => true, 'sort_order' => 1,
        'image' => realUploadedFileWithContent('banner.jpg', maliciousSvgContent(), 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(PromotionalBanner::where('title', 'Evil Sale')->exists())->toBeFalse();
});

// ── Product media ────────────────────────────────────────────────────────────

test('a product image upload accepts a valid JPEG and still accepts a genuine video file', function () {
    $admin = imageSecurityAdmin();
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.products.store'), [
        'name' => 'Good Product', 'slug' => 'good-product',
        'category_id' => $category->id, 'sub_category_id' => $subCategory->id,
        'price' => 100, 'stock_quantity' => 10,
        'images' => [
            UploadedFile::fake()->image('product.jpg'),
            UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
        ],
    ]);

    $response->assertRedirect();
    expect(Product::where('slug', 'good-product')->exists())->toBeTrue();
});

test('a product image upload rejects an SVG disguised with a .jpg extension', function () {
    $admin = imageSecurityAdmin();
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    actingAs($admin, 'admin');

    $response = $this->post(route('admin.products.store'), [
        'name' => 'Evil Product', 'slug' => 'evil-product',
        'category_id' => $category->id, 'sub_category_id' => $subCategory->id,
        'price' => 100, 'stock_quantity' => 10,
        'images' => [
            realUploadedFileWithContent('product.jpg', maliciousSvgContent(), 'image/jpeg'),
        ],
    ]);

    $response->assertSessionHasErrors('images.0');
    expect(Product::where('slug', 'evil-product')->exists())->toBeFalse();
});
