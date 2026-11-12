<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * The new and edit forms open as modals over the list, so saving either one
 * returns to the list (which closes the modal) instead of reopening an editor.
 */
beforeEach(function () {
    $this->admin = createAdmin();
    $this->product = Product::factory()->create(['is_active' => true]);
});

test('creating a landing page returns to the list instead of opening the editor', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.landing-pages.store'), [
            'title' => 'Promo', 'slug' => 'promo', 'product_id' => $this->product->id, 'is_active' => true,
        ])
        ->assertRedirect(route('admin.landing-pages.index'))
        ->assertSessionHas('success');

    expect(LandingPage::where('slug', 'promo')->exists())->toBeTrue();
});

test('saving a landing page returns to the list', function () {
    $page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo', 'product_id' => $this->product->id, 'is_active' => true,
        'sections' => LandingPage::defaultSections(),
    ]);

    actingAs($this->admin, 'admin')
        ->put(route('admin.landing-pages.update', $page), [
            'title' => 'Promo 2', 'slug' => 'promo', 'product_id' => $this->product->id, 'is_active' => true,
            'sections' => LandingPage::defaultSections(),
        ])
        ->assertRedirect(route('admin.landing-pages.index'));

    expect($page->fresh()->title)->toBe('Promo 2');
});

test('the list carries each page product image', function () {
    ProductMedia::create(['product_id' => $this->product->id, 'type' => 'image', 'path' => '/storage/products/a.jpg', 'is_primary' => true, 'sort_order' => 0]);
    LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo', 'product_id' => $this->product->id, 'is_active' => true,
        'sections' => LandingPage::defaultSections(),
    ]);

    actingAs($this->admin, 'admin')->get(route('admin.landing-pages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pages.0.slug', 'promo')
            ->where('pages.0.product.name', $this->product->name)
            ->where('pages.0.product.image', '/storage/products/a.jpg'));
});
