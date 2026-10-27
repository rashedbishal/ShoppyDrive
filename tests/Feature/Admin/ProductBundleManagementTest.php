<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-bundles@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can create a fixed-price bundle with its components', function () {
    $productA = Product::factory()->create(['price' => 150]);
    $productB = Product::factory()->create(['price' => 50]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Summer Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 120,
        'is_active' => true,
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ])->assertRedirect(route('admin.product-bundles.index'));

    $bundle = ProductBundle::query()->where('name', 'Summer Combo')->firstOrFail();
    expect($bundle->discount_type->value)->toBe('fixed_price');
    expect((float) $bundle->bundle_price)->toBe(120.0);
    expect($bundle->items)->toHaveCount(2);
    expect($bundle->items->firstWhere('product_id', $productB->id)->quantity)->toBe(2);
});

test('admin can create a free-delivery bundle without a bundle price', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Free Ship Combo',
        'discount_type' => 'free_delivery',
        'is_active' => true,
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect(route('admin.product-bundles.index'));

    $bundle = ProductBundle::query()->where('name', 'Free Ship Combo')->firstOrFail();
    expect($bundle->discount_type->value)->toBe('free_delivery');
    expect($bundle->bundle_price)->toBeNull();
});

test('a bundle requires at least two items', function () {
    $product = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Single Item',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    expect(ProductBundle::query()->count())->toBe(0);
});

test('a fixed-price bundle requires a bundle price', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'No Price Combo',
        'discount_type' => 'fixed_price',
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('bundle_price');
});

test('updating a bundle replaces its items', function () {
    $bundle = ProductBundle::factory()->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $productC = Product::factory()->create();
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $productA->id]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $productB->id]);

    actingAs($this->admin, 'admin');

    $this->put(route('admin.product-bundles.update', $bundle->id), [
        'name' => $bundle->name,
        'discount_type' => 'fixed_price',
        'bundle_price' => 99,
        'is_active' => true,
        'items' => [
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $productC->id, 'variant_id' => null, 'quantity' => 3],
        ],
    ])->assertRedirect(route('admin.product-bundles.index'));

    $bundle->refresh();
    expect($bundle->items)->toHaveCount(2);
    expect($bundle->items->pluck('product_id')->sort()->values()->all())->toBe([$productB->id, $productC->id]);
});

test('admin can bulk-deactivate and delete bundles', function () {
    $bundleA = ProductBundle::factory()->create(['is_active' => true]);
    $bundleB = ProductBundle::factory()->create(['is_active' => true]);
    ProductBundleItem::factory()->count(2)->create(['product_bundle_id' => $bundleA->id]);
    ProductBundleItem::factory()->count(2)->create(['product_bundle_id' => $bundleB->id]);

    actingAs($this->admin, 'admin');

    $this->patch(route('admin.product-bundles.updateStatusMany'), [
        'ids' => [$bundleA->id, $bundleB->id],
        'active' => false,
    ])->assertRedirect();

    expect($bundleA->refresh()->is_active)->toBeFalse();
    expect($bundleB->refresh()->is_active)->toBeFalse();

    $this->delete(route('admin.product-bundles.destroyMany'), [
        'ids' => [$bundleA->id, $bundleB->id],
    ])->assertRedirect();

    expect(ProductBundle::query()->count())->toBe(0);
    expect(ProductBundleItem::query()->count())->toBe(0);
});
