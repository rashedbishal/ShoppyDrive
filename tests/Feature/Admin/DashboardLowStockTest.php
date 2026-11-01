<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
});

/**
 * LOW-03: `products.stock_quantity`/`low_stock_threshold` are only a
 * denormalized sum/copy for a variant product, so every fixture here gives
 * the parent a deliberately stale value and sets the real numbers on the
 * variants instead — exactly like ProductAvailableStockTest's HIGH-02
 * fixtures, which this finding is the dashboard-side counterpart to.
 *
 * @param  array<int, array{stock: int, threshold: int|null}>  $variants
 */
function lowStockTestProduct(int $parentStock, array $variants = [], int $parentThreshold = 5): Product
{
    $product = Product::factory()->create([
        'stock_quantity' => $parentStock,
        'low_stock_threshold' => $parentThreshold,
        'is_active' => true,
    ]);

    foreach ($variants as $index => $variant) {
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => $variant['stock'],
            // `??` would coalesce an explicit null (the "no threshold set"
            // fixture) back to 5, so the key's presence must be checked
            // instead of its value.
            'low_stock_threshold' => array_key_exists('threshold', $variant) ? $variant['threshold'] : 5,
            'is_default' => $index === 0,
        ]);
    }

    return $product;
}

test('a variant below its own threshold is flagged even though the summed stock is comfortably above the parent threshold', function () {
    // Sum is 29, parent threshold 5 — the old summed comparison would have
    // missed this entirely, even though the "Small" variant is critically low.
    $product = lowStockTestProduct(parentStock: 100, variants: [
        ['stock' => 25, 'threshold' => 5],
        ['stock' => 4, 'threshold' => 5],
    ]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 1)
        ->where('lowStockProducts.0.id', $product->id));
});

test('no variant breaching its own threshold means the product is not flagged, even if the parent sum looks low', function () {
    // Parent's own stale stock_quantity (3) is below its own threshold (5),
    // but neither real variant is below its own threshold — must not flag.
    lowStockTestProduct(parentStock: 3, parentThreshold: 5, variants: [
        ['stock' => 20, 'threshold' => 5],
        ['stock' => 30, 'threshold' => 10],
    ]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 0)
        ->where('lowStockProducts', []));
});

test('each variant is checked against its own threshold, not a single shared one', function () {
    // Second variant's stock (8) would pass a threshold of 5, but fails its
    // own higher threshold of 10.
    $product = lowStockTestProduct(parentStock: 50, variants: [
        ['stock' => 20, 'threshold' => 5],
        ['stock' => 8, 'threshold' => 10],
    ]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 1)
        ->where('lowStockProducts.0.id', $product->id));
});

test('a product without variants still uses its own stock and threshold', function () {
    $low = lowStockTestProduct(parentStock: 2, parentThreshold: 5);
    $healthy = lowStockTestProduct(parentStock: 50, parentThreshold: 5);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 1)
        ->where('lowStockProducts.0.id', $low->id));

    expect($healthy->fresh())->not->toBeNull();
});

test('a variant with no threshold set is never flagged, matching the real-time low-stock alert', function () {
    lowStockTestProduct(parentStock: 50, variants: [
        ['stock' => 0, 'threshold' => null],
    ]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 0));
});

test('a straightforwardly low variant-less product is still flagged, same as before', function () {
    $product = lowStockTestProduct(parentStock: 1, parentThreshold: 5);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 1)
        ->where('lowStockProducts.0.id', $product->id)
        ->where('lowStockProducts.0.stock_quantity', 1)
        ->where('lowStockProducts.0.low_stock_threshold', 5));
});

test('an inactive low-stock product is still excluded, same as before', function () {
    lowStockTestProduct(parentStock: 1, parentThreshold: 5)->update(['is_active' => false]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.low_stock_count', 0));
});
