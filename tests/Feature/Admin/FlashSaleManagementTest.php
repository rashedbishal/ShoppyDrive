<?php

use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\HomepageSetting;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->flashSale = FlashSale::create([
        'name' => 'Weekend Flash Sale',
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
        'is_active' => true,
    ]);

    $this->setting = HomepageSetting::create([
        'section_key' => 'flash_sale',
        'section_name' => 'Weekend Flash Sale',
        'title' => 'Weekend Flash Sale',
        'is_visible' => true,
        'sort_order' => 1,
        'metadata' => ['flash_sale_id' => $this->flashSale->id],
    ]);

    $this->product = Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'price' => 100,
    ]);
});

test('admin can see flash sale edit page with flash sale data', function () {
    actingAs($this->admin, 'admin');

    $response = get(route('admin.homepage.edit', $this->setting));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/homepage/edit')
        ->has('flashSale')
        ->has('products')
    );
});

test('flash sale appears on homepage when active and visible', function () {
    $this->flashSale->update([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $this->flashSale->items()->create([
        'product_id' => $this->product->id,
        'sale_price' => 80,
        'stock_limit' => 10,
    ]);

    $response = get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->has('activeFlashSale')
        ->where('activeFlashSale.items.0.product_id', $this->product->id)
    );
});

test('admin can search products for flash sale', function () {
    actingAs($this->admin, 'admin');

    $response = get(route('admin.homepage.products.search', ['q' => $this->product->name]));

    $response->assertSuccessful();
    $response->assertJsonFragment(['id' => $this->product->id, 'name' => $this->product->name]);
});
