<?php

use App\Models\Brand;
use App\Models\Product;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
});

/**
 * @return array<string, mixed>
 */
function listPositionProductPayload(Product $product): array
{
    return [
        'stock_token' => ProductStockToken::for($product),
        'name' => $product->name.' renamed',
        'slug' => $product->slug,
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => $product->category_id,
        'sub_category_id' => $product->sub_category_id,
        'price' => $product->price,
        'stock_quantity' => $product->stock_quantity,
        'is_active' => true,
    ];
}

test('saving a product returns to the page and filters the product list was on', function () {
    $products = Product::factory()->count(25)->create();
    $product = $products->first();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index', ['page' => 2, 'status' => 'active']))
        ->assertOk();

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), listPositionProductPayload($product))
        ->assertRedirect(route('admin.products.index').'?page=2&status=active');
});

test('deleting a product returns to the page the product list was on', function () {
    $products = Product::factory()->count(25)->create();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index', ['page' => 2]))
        ->assertOk();

    actingAs($this->admin, 'admin')
        ->delete(route('admin.products.destroy', $products->first()))
        ->assertRedirect(route('admin.products.index').'?page=2');
});

test('going back to the plain list forgets the earlier page', function () {
    $products = Product::factory()->count(25)->create();
    $product = $products->first();

    actingAs($this->admin, 'admin')->get(route('admin.products.index', ['page' => 2]))->assertOk();
    actingAs($this->admin, 'admin')->get(route('admin.products.index'))->assertOk();

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), listPositionProductPayload($product))
        ->assertRedirect(route('admin.products.index'));
});

test('a prefetch of the plain list does not forget the page the admin is on', function () {
    $products = Product::factory()->count(25)->create();
    $product = $products->first();

    actingAs($this->admin, 'admin')->get(route('admin.products.index', ['page' => 2]))->assertOk();
    actingAs($this->admin, 'admin')
        ->withHeader('Purpose', 'prefetch')
        ->get(route('admin.products.index'))
        ->assertOk();

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), listPositionProductPayload($product))
        ->assertRedirect(route('admin.products.index').'?page=2');
});

test('each list remembers its own position', function () {
    Brand::factory()->count(25)->create();
    $products = Product::factory()->count(5)->create();
    $product = $products->first();

    actingAs($this->admin, 'admin')->get(route('admin.brands.index', ['page' => 2]))->assertOk();

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), listPositionProductPayload($product))
        ->assertRedirect(route('admin.products.index'));
});

test('creating a product still opens the first page, where the new product is listed', function () {
    Product::factory()->count(25)->create();
    $template = Product::factory()->make();

    actingAs($this->admin, 'admin')->get(route('admin.products.index', ['page' => 2]))->assertOk();

    actingAs($this->admin, 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Brand new product',
            'slug' => 'brand-new-product',
            'short_description' => 'A short description.',
            'description' => '<p>A full description.</p>',
            'images' => [UploadedFile::fake()->image('product.jpg', 400, 400)],
            'category_id' => $template->category_id,
            'sub_category_id' => $template->sub_category_id,
            'price' => 100,
            'stock_quantity' => 5,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.products.index'));
});

test('the edit page receives the remembered list url for its back and cancel links', function () {
    $products = Product::factory()->count(25)->create();

    actingAs($this->admin, 'admin')->get(route('admin.products.index', ['page' => 2, 'status' => 'active']))->assertOk();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.edit', $products->first()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('admin_list_urls', ['admin.products.index' => route('admin.products.index').'?page=2&status=active'])
        );
});

test('a page past the last one opens the last page that still has rows', function () {
    Product::factory()->count(25)->create();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index', ['page' => 3, 'status' => 'active']))
        ->assertRedirect(route('admin.products.index', ['page' => 2, 'status' => 'active']));
});

test('a page past the only page opens the plain list', function () {
    Product::factory()->count(3)->create();

    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index', ['page' => 2]))
        ->assertRedirect(route('admin.products.index'));
});

test('an empty list on page one is shown, not redirected', function () {
    actingAs($this->admin, 'admin')
        ->get(route('admin.products.index'))
        ->assertOk();
});
