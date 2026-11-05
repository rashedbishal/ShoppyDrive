<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Deactivating a brand/category/sub-category must hide every product under
 * it from the storefront, and the brand/category/sub-category's own URL
 * must 404 — not just disappear from menus. A category going inactive must
 * cascade to its sub-categories and their products too.
 */

function taxonomyProduct(array $overrides = []): Product
{
    $category = $overrides['category'] ?? Category::factory()->create(['is_active' => true]);
    $subCategory = $overrides['subCategory'] ?? SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true]);
    $brand = $overrides['brand'] ?? Brand::factory()->create(['is_active' => true]);

    return Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'category_id' => $category->id,
        'sub_category_id' => $subCategory->id,
        'brand_id' => $brand->id,
        ...array_diff_key($overrides, ['category' => null, 'subCategory' => null, 'brand' => null]),
    ]);
}

// ── Product::isPubliclyVisible() / scopeVisible() ───────────────────────────

test('a product is invisible once its brand is deactivated', function () {
    $brand = Brand::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['brand' => $brand]);

    expect($product->isPubliclyVisible())->toBeTrue();
    expect(Product::query()->visible()->whereKey($product->id)->exists())->toBeTrue();

    $brand->update(['is_active' => false]);
    $product->refresh()->load('brand');

    expect($product->isPubliclyVisible())->toBeFalse();
    expect(Product::query()->visible()->whereKey($product->id)->exists())->toBeFalse();
});

test('a product is invisible once its sub-category is deactivated', function () {
    $subCategory = SubCategory::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['subCategory' => $subCategory, 'category' => $subCategory->category]);

    $subCategory->update(['is_active' => false]);
    $product->refresh()->load('subCategory');

    expect($product->isPubliclyVisible())->toBeFalse();
    expect(Product::query()->visible()->whereKey($product->id)->exists())->toBeFalse();
});

test('a product is invisible once its category is deactivated, even though the product and sub-category are untouched', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true]);
    $product = taxonomyProduct(['category' => $category, 'subCategory' => $subCategory]);

    $category->update(['is_active' => false]);
    $product->refresh()->load('category');

    expect($product->isPubliclyVisible())->toBeFalse();
    expect($subCategory->fresh()->is_active)->toBeTrue(); // the sub-category row itself is untouched
    expect(Product::query()->visible()->whereKey($product->id)->exists())->toBeFalse();
});

test('a product with no brand at all is unaffected by brand status', function () {
    $product = taxonomyProduct(['brand_id' => null, 'brand' => null]);
    $product->setRelation('brand', null);

    expect($product->isPubliclyVisible())->toBeTrue();
});

// ── Product detail page ──────────────────────────────────────────────────

test('a product page 404s once its category is deactivated', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['category' => $category]);

    $this->get(route('product.show', $product->slug))->assertOk();

    $category->update(['is_active' => false]);

    $this->get(route('product.show', $product->slug))->assertNotFound();
});

// ── Brand page ───────────────────────────────────────────────────────────

test('visiting an inactive brand\'s page 404s, not just disappears from the brand index', function () {
    $brand = Brand::factory()->create(['is_active' => false]);

    $this->get(route('brand.show', $brand->slug))->assertNotFound();
});

test('an active brand\'s page hides a product whose category was deactivated', function () {
    $brand = Brand::factory()->create(['is_active' => true]);
    $hiddenCategory = Category::factory()->create(['is_active' => true]);
    $hidden = taxonomyProduct(['brand' => $brand, 'category' => $hiddenCategory]);
    $visible = taxonomyProduct(['brand' => $brand]); // its own, still-active category
    $hiddenCategory->update(['is_active' => false]);

    $this->get(route('brand.show', $brand->slug))->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 1)
        ->where('products.data.0.id', $visible->id));
});

// ── Category / sub-category listing pages ───────────────────────────────

test('visiting an inactive category\'s URL 404s', function () {
    $category = Category::factory()->create(['is_active' => false]);

    $this->get(route('category.show', $category->slug))->assertNotFound();
});

test('visiting an inactive sub-category\'s URL 404s', function () {
    $subCategory = SubCategory::factory()->create(['is_active' => false]);

    $this->get(route('category.show', $subCategory->slug))->assertNotFound();
});

test('visiting an active sub-category\'s URL 404s once its parent category is deactivated', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    $this->get(route('category.show', $subCategory->slug))->assertOk();

    $category->update(['is_active' => false]);

    $this->get(route('category.show', $subCategory->slug))->assertNotFound();
});

// ── Cart / wishlist / add-to-cart ────────────────────────────────────────

test('a cart item is dropped once its product\'s brand is deactivated, same as a deleted product', function () {
    $user = User::factory()->create();
    $brand = Brand::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['brand' => $brand, 'price' => 100]);

    $cart = Cart::create(['user_id' => $user->id, 'session_id' => 'test-session']);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => $product->price]);

    $brand->update(['is_active' => false]);

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('cart.items', 0)
        ->where('cart.item_count', 0));
});

test('adding a product under a deactivated category to the cart is refused', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['category' => $category]);
    $category->update(['is_active' => false]);

    $this->actingAs($user)->post(route('cart.store'), [
        'product_id' => $product->id, 'variant_id' => null, 'quantity' => 1,
    ])->assertSessionHasErrors('product_id');
});

test('a wishlisted product under a deactivated sub-category no longer shows on the wishlist page', function () {
    $user = User::factory()->create();
    $subCategory = SubCategory::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['subCategory' => $subCategory, 'category' => $subCategory->category]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    $subCategory->update(['is_active' => false]);

    $this->actingAs($user)->get(route('account.wishlist'))->assertInertia(fn (Assert $page) => $page
        ->has('wishlist', 0));
});

// ── Landing pages ────────────────────────────────────────────────────────

test('a landing page 404s once its promoted product\'s brand is deactivated', function () {
    $brand = Brand::factory()->create(['is_active' => true]);
    $product = taxonomyProduct(['brand' => $brand]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    $this->get(route('lp.show', $page->slug))->assertOk();

    $brand->update(['is_active' => false]);

    $this->get(route('lp.show', $page->slug))->assertNotFound();
});
