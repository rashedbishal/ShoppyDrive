<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * The flash-sale form warns when a sale price is below cost. Cost prices are
 * otherwise shown only on the product edit screen, so they reach this form
 * only for admins allowed to edit products.
 */

function flashSaleOnlyAdmin(): Admin
{
    return createAdmin(['permissions' => ['flash_sales' => ['view', 'create', 'update', 'delete']]]);
}

test('the product search gives a product-editing admin each variant\'s cost and the whole product\'s highest one', function () {
    $product = Product::factory()->create(['name' => 'Cost Tee', 'price' => 1000, 'cost_price' => 100, 'is_active' => true]);
    $cheap = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'cost_price' => 400]);
    $dear = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'cost_price' => 700]);
    actingAs(createAdmin(), 'admin');

    $response = get(route('admin.flash-sales.products.search', ['q' => 'Cost Tee']));

    $response->assertOk()
        ->assertJsonPath('0.cost_price', 700)
        ->assertJsonPath('0.variants.0.id', $cheap->id)
        ->assertJsonPath('0.variants.0.cost_price', 400)
        ->assertJsonPath('0.variants.1.id', $dear->id)
        ->assertJsonPath('0.variants.1.cost_price', 700);
});

test('the product search uses a product\'s own cost when it has no variants, and null when none was entered', function () {
    Product::factory()->create(['name' => 'Alpha Plain', 'price' => 500, 'cost_price' => 320, 'is_active' => true]);
    Product::factory()->create(['name' => 'Beta Plain', 'price' => 500, 'cost_price' => null, 'is_active' => true]);
    actingAs(createAdmin(), 'admin');

    $response = get(route('admin.flash-sales.products.search', ['q' => 'Plain']));

    $response->assertOk()
        ->assertJsonPath('0.name', 'Alpha Plain')
        ->assertJsonPath('0.cost_price', 320)
        ->assertJsonPath('1.name', 'Beta Plain')
        ->assertJsonPath('1.cost_price', null);
});

test('an admin who cannot edit products never receives cost prices from the product search', function () {
    // SKU and stock are pinned: the factory's random ones (e.g. "VAR-06500", stock 650)
    // could contain the sentinel digits below and fail the substring check at random.
    $product = Product::factory()->create(['name' => 'Secret Cost', 'sku' => 'SECRET-SKU', 'stock_quantity' => 7, 'price' => 1000, 'cost_price' => 600, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SECRET-VAR', 'stock_quantity' => 7, 'price' => 1000, 'cost_price' => 650]);
    actingAs(flashSaleOnlyAdmin(), 'admin');

    $response = get(route('admin.flash-sales.products.search', ['q' => 'Secret Cost']));

    $response->assertOk()
        ->assertJsonPath('0.cost_price', null)
        ->assertJsonPath('0.variants.0.cost_price', null);
    expect($response->getContent())->not->toContain('600')->not->toContain('650');
});

test('the edit page gives each item its cost for a product-editing admin, and none otherwise', function () {
    $product = Product::factory()->create(['price' => 1000, 'cost_price' => 100]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'cost_price' => 450]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'cost_price' => 800]);
    $flashSale = FlashSale::factory()->create();
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => null, 'sale_price' => 900]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 900]);

    actingAs(createAdmin(), 'admin');
    get(route('admin.flash-sales.edit', $flashSale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flashSale.items.0.cost_price', 800)
            ->missing('flashSale.items.0.product.cost_price')
            ->where('flashSale.items.1.cost_price', 450)
            ->etc());

    actingAs(flashSaleOnlyAdmin(), 'admin');
    get(route('admin.flash-sales.edit', $flashSale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flashSale.items.0.cost_price', null)
            ->where('flashSale.items.1.cost_price', null)
            ->etc());
});
