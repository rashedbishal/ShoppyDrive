<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('the product list includes each product\'s primary image', function () {
    $product = Product::factory()->create();
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/secondary.jpg', 'type' => 'image', 'sort_order' => 1, 'is_primary' => false]);
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/primary.jpg', 'type' => 'image', 'sort_order' => 0, 'is_primary' => true]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.primary_image_url', '/storage/primary.jpg'));
});

test('a product with no media gets a null primary image instead of erroring', function () {
    Product::factory()->create();

    actingAs($this->admin, 'admin');

    $this->get(route('admin.products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.primary_image_url', null));
});

test('a search stays inside the chosen status filter, whether it matches by name or sku', function (array $match) {
    Product::factory()->create([...$match, 'is_active' => true]);
    $inactive = Product::factory()->create([...$match, 'is_active' => false, 'sku' => ($match['sku'] ?? 'x').'-2']);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.products.index', ['search' => 'Findme', 'status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $inactive->id));
})->with([
    'by name' => [['name' => 'Findme Kettle']],
    'by sku' => [['sku' => 'Findme-1']],
]);

test('products created in the same second list newest-first by id, and an update does not move one', function () {
    $createdAt = now()->subDay();
    $products = Product::factory()->count(3)->create(['created_at' => $createdAt]);
    $expectedOrder = $products->pluck('id')->sortDesc()->values()->all();

    actingAs($this->admin, 'admin');

    $listedIds = fn (): array => collect($this->get(route('admin.products.index'))->viewData('page')['props']['products']['data'])->pluck('id')->all();

    expect($listedIds())->toBe($expectedOrder);

    $products->get(1)->update(['short_description' => 'Edited']);

    expect($listedIds())->toBe($expectedOrder);
});
