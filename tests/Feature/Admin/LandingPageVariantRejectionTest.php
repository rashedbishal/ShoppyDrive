<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FSA-08 (superseded 2026-11-11): landing pages used to refuse a product with
 * colour/size variants because the funnel had no variant selector. It has one
 * now (one row per variant with its own quantity), so variants are accepted.
 * A landing page may also promote an inactive product; only a deleted product
 * is refused.
 */
beforeEach(function () {
    $this->admin = createAdmin();
    $this->plainProduct = Product::factory()->create(['is_active' => true]);
    $this->variantProduct = Product::factory()->create(['is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $this->variantProduct->id]);
});

function landingPagePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $product->id,
        'is_active' => true,
    ], $overrides);
}

test('a landing page can be created for a variant product', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.landing-pages.store'), landingPagePayload($this->variantProduct))
        ->assertSessionHasNoErrors();

    expect(LandingPage::query()->where('product_id', $this->variantProduct->id)->exists())->toBeTrue();
});

test('a landing page can be created for an inactive product', function () {
    $inactive = Product::factory()->create(['is_active' => false]);

    actingAs($this->admin, 'admin')
        ->post(route('admin.landing-pages.store'), landingPagePayload($inactive))
        ->assertSessionHasNoErrors();

    expect(LandingPage::query()->where('product_id', $inactive->id)->exists())->toBeTrue();
});

test('a deleted product is refused', function () {
    $deleted = Product::factory()->create();
    $deleted->delete();

    actingAs($this->admin, 'admin')
        ->post(route('admin.landing-pages.store'), landingPagePayload($deleted))
        ->assertSessionHasErrors('product_id');

    expect(LandingPage::query()->count())->toBe(0);
});

test('the product list offered to the admin includes inactive products', function () {
    $inactive = Product::factory()->create(['is_active' => false, 'name' => 'Switched Off Item']);

    actingAs($this->admin, 'admin')
        ->get(route('admin.landing-pages.create'))
        ->assertInertia(fn ($page) => $page->where('products', fn ($products) => collect($products)->pluck('id')->contains($inactive->id)));
});

test('the most units per order defaults to 100 and the admin can change it, from 1 to 100', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.landing-pages.store'), landingPagePayload($this->plainProduct))
        ->assertSessionHasNoErrors();

    $page = LandingPage::query()->sole();
    expect($page->max_order_quantity)->toBe(100);

    $this->put(route('admin.landing-pages.update', $page), [
        'title' => $page->title, 'slug' => $page->slug, 'product_id' => $page->product_id,
        'is_active' => true, 'max_order_quantity' => 7, 'sections' => $page->sections,
    ])->assertSessionHasNoErrors();
    expect($page->refresh()->max_order_quantity)->toBe(7);

    foreach ([0, 101] as $bad) {
        $this->put(route('admin.landing-pages.update', $page), [
            'title' => $page->title, 'slug' => $page->slug, 'product_id' => $page->product_id,
            'is_active' => true, 'max_order_quantity' => $bad, 'sections' => $page->sections,
        ])->assertSessionHasErrors('max_order_quantity');
    }
    expect($page->refresh()->max_order_quantity)->toBe(7);
});
