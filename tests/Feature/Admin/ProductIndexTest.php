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
