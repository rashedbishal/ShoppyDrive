<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * FSA-08: a landing page has no variant selector, so every order placed
 * through one for a variant product fails deterministically at
 * InventoryService::decrementForOrderItem() — with mobile banking having
 * already collected payment first (FSA-07). Blocked at admin save instead
 * of building a variant selector into the funnel.
 */
beforeEach(function () {
    $this->admin = createAdmin();
    $this->plainProduct = Product::factory()->create(['is_active' => true]);
    $this->variantProduct = Product::factory()->create(['is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $this->variantProduct->id]);
});

test('creating a landing page for a variant product is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.landing-pages.store'), [
        'title' => 'Promo',
        'slug' => 'promo-variant-'.uniqid(),
        'product_id' => $this->variantProduct->id,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('product_id');
    expect(LandingPage::query()->count())->toBe(0);
});

test('creating a landing page for a variant-less product still works', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.landing-pages.store'), [
        'title' => 'Promo',
        'slug' => 'promo-plain-'.uniqid(),
        'product_id' => $this->plainProduct->id,
        'is_active' => true,
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(LandingPage::query()->where('product_id', $this->plainProduct->id)->exists())->toBeTrue();
});

test('repointing an existing landing page at a variant product is rejected', function () {
    actingAs($this->admin, 'admin');
    $page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $this->plainProduct->id,
        'is_active' => true, 'sections' => LandingPage::defaultSections(),
    ]);

    $response = $this->put(route('admin.landing-pages.update', $page), [
        'title' => $page->title,
        'slug' => $page->slug,
        'product_id' => $this->variantProduct->id,
        'is_active' => true,
        'sections' => $page->sections,
    ]);

    $response->assertSessionHasErrors('product_id');
    expect($page->refresh()->product_id)->toBe($this->plainProduct->id);
});

test('saving other fields on a landing page already pointed at a variant product is blocked until the product changes', function () {
    actingAs($this->admin, 'admin');
    // Simulates a pre-existing row created before this validation existed
    // (bypassing the model-level guard the way a raw seed/import would).
    $page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $this->variantProduct->id,
        'is_active' => true, 'sections' => LandingPage::defaultSections(),
    ]);

    $response = $this->put(route('admin.landing-pages.update', $page), [
        'title' => 'Promo (renamed)',
        'slug' => $page->slug,
        'product_id' => $this->variantProduct->id,
        'is_active' => true,
        'sections' => $page->sections,
    ]);

    $response->assertSessionHasErrors('product_id');
    expect($page->refresh()->title)->toBe('Promo');
});
