<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
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

test('a bundle rejects the same product and variant combination added twice', function () {
    $product = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Duplicate Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    expect(ProductBundle::query()->count())->toBe(0);
});

test('a bundle rejects the same product and the same variant pinned twice', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $other = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Duplicate Variant Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
            ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
            ['product_id' => $other->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items');

    expect(ProductBundle::query()->count())->toBe(0);
});

test('a single product with multiple variants can satisfy the two-item minimum on its own', function () {
    $product = Product::factory()->create();
    $small = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);
    $large = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Two Sizes Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => $small->id, 'quantity' => 1],
            ['product_id' => $product->id, 'variant_id' => $large->id, 'quantity' => 1],
        ],
    ])->assertRedirect(route('admin.product-bundles.index'));

    $bundle = ProductBundle::query()->where('name', 'Two Sizes Combo')->firstOrFail();
    expect($bundle->items)->toHaveCount(2);
    expect($bundle->items->pluck('variant_id')->sort()->values()->all())
        ->toBe([$small->id, $large->id]);
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

test('a bundle component cannot require more units than the product has in stock', function () {
    // BIZ-002 retest (2026-11-09): a tester found that a bundle recipe
    // asking for more of a product than it has in stock can never actually
    // be bought — there's no per-unit picker anywhere a customer could buy
    // less than one full bundle set, so the admin shouldn't be able to
    // create that recipe in the first place.
    $productA = Product::factory()->create(['stock_quantity' => 5]);
    $productB = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Overcommitted Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 10],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items.0.quantity');

    expect(ProductBundle::query()->count())->toBe(0);
});

test('a bundle component is capped by the chosen variant\'s own stock, not the product\'s', function () {
    $product = Product::factory()->create(['stock_quantity' => 500]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3]);
    $other = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Variant Overcommitted Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5],
            ['product_id' => $other->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items.0.quantity');

    expect(ProductBundle::query()->count())->toBe(0);
});

test('a bundle component within stock is accepted, and updating one to exceed stock is refused the same way', function () {
    $productA = Product::factory()->create(['stock_quantity' => 5]);
    $productB = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Fits In Stock Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 5],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect(route('admin.product-bundles.index'));

    $bundle = ProductBundle::query()->where('name', 'Fits In Stock Combo')->firstOrFail();

    $this->put(route('admin.product-bundles.update', $bundle->id), [
        'name' => $bundle->name,
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'is_active' => true,
        'items' => [
            ['product_id' => $productA->id, 'variant_id' => null, 'quantity' => 6],
            ['product_id' => $productB->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items.0.quantity');

    expect($bundle->refresh()->items->firstWhere('product_id', $productA->id)->quantity)->toBe(5);
});

test('a variant-less selection of a product that has variants has no stock of its own, so any quantity is refused', function () {
    // Mirrors InventoryService::assertAvailable()'s same rule at checkout —
    // a product with real variants is never purchasable without picking one.
    $product = Product::factory()->create(['stock_quantity' => 50]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 20]);
    $other = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.product-bundles.store'), [
        'name' => 'Missing Variant Combo',
        'discount_type' => 'fixed_price',
        'bundle_price' => 50,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
            ['product_id' => $other->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertSessionHasErrors('items.0.quantity');

    expect(ProductBundle::query()->count())->toBe(0);
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
