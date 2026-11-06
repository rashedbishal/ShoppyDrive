<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\ProductCardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function cardProduct(array $attributes = []): Product
{
    $product = Product::factory()->create([
        'description' => 'A very long description that no card shows.',
        'short_description' => 'Short text',
        'meta_title' => 'Search title',
        'meta_description' => 'Search text',
        'brand_id' => Brand::factory()->create(['name' => 'Acme', 'description' => 'Brand story'])->id,
        'category_id' => Category::factory()->create(['name' => 'Kitchen', 'description' => 'Category story', 'meta_title' => 'Category search'])->id,
        'is_active' => true,
        'is_featured' => true,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/second.webp', 'type' => 'image', 'sort_order' => 1, 'is_primary' => false]);
    ProductMedia::create(['product_id' => $product->id, 'path' => '/storage/main.webp', 'type' => 'image', 'sort_order' => 0, 'is_primary' => true]);

    return $product;
}

test('a card leaves out what no customer screen reads', function () {
    $card = ProductCardData::make(cardProduct()->load(['images', 'brand', 'category']));

    expect($card)->not->toHaveKeys(['description', 'meta_title', 'meta_description', 'meta_keywords', 'specifications', 'created_at', 'updated_at', 'published_at', 'media'])
        ->and($card['category'])->toHaveKeys(['id', 'name', 'slug'])->not->toHaveKeys(['description', 'meta_title', 'created_at'])
        ->and($card['brand'])->toHaveKeys(['id', 'name', 'slug'])->not->toHaveKey('description')
        ->and($card['images'][0])->not->toHaveKeys(['created_at', 'updated_at', 'product_id']);
});

test('a card keeps everything the card, cart, checkout and analytics read', function () {
    $product = cardProduct(['sku' => 'SKU-1', 'compare_price' => 900, 'price' => 700, 'free_delivery' => true]);
    $card = ProductCardData::make($product->fresh()->load(['images', 'brand', 'category', 'variants']));

    expect($card)->toHaveKeys([
        'id', 'name', 'slug', 'sku', 'short_description', 'price', 'compare_price', 'stock_quantity', 'available_stock',
        'low_stock_threshold', 'free_delivery', 'has_color_variant', 'has_size_variant', 'variants', 'images', 'brand', 'category',
    ])->and($card['primary_image_url'])->toBe('/storage/main.webp')
        ->and($card['sku'])->toBe('SKU-1')
        ->and($card['free_delivery'])->toBeTrue();
});

test('variants reach the card whole, because cart pricing reads them', function () {
    $product = cardProduct();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3, 'is_default' => true]);

    $card = ProductCardData::make($product->load(['images', 'variants.media']));

    expect($card['variants'][0])->toHaveKeys(['id', 'price', 'stock_quantity', 'is_default', 'attributes'])
        ->and($card['variants'][0]['id'])->toBe($variant->id);
});

test('a relation nobody loaded is not fetched', function () {
    $product = cardProduct();
    $card = ProductCardData::make($product->fresh());

    expect($card)->not->toHaveKeys(['brand', 'category', 'images', 'primary_image_url'])
        ->and($product->fresh()->relationLoaded('brand'))->toBeFalse();
});

test('every customer list sends slim cards', function (Closure $visit, string $path) {
    $product = cardProduct();

    $visit($this, $product)->assertInertia(fn (Assert $page) => $page
        ->where("{$path}.id", $product->id)
        ->where("{$path}.primary_image_url", '/storage/main.webp')
        ->missing("{$path}.description")
        ->missing("{$path}.meta_description"));
})->with([
    'listing' => [fn ($test) => $test->get(route('products.index')), 'products.data.0'],
    'brand' => [fn ($test, $product) => $test->get(route('brand.show', $product->brand->slug)), 'products.data.0'],
    'search' => [fn ($test, $product) => $test->get('/search?q='.urlencode($product->name)), 'products.data.0'],
    'home' => [fn ($test) => $test->get(route('home')), 'featuredProducts.0'],
    'flash sale page' => [fn ($test) => $test->get(route('flash-sales.index')), 'featuredProducts.0'],
]);

test('related products on a product page are slim, and the product itself stays whole', function () {
    $category = Category::factory()->create();
    $product = cardProduct(['category_id' => $category->id]);
    $related = cardProduct(['category_id' => $category->id]);

    $this->get(route('product.show', $product->slug))->assertInertia(fn (Assert $page) => $page
        ->where('product.description', fn (string $description): bool => str_contains($description, 'A very long description'))
        ->where('relatedProducts.0.id', $related->id)
        ->missing('relatedProducts.0.description'));
});

test('wishlist cards are slim', function () {
    $user = User::factory()->create();
    $product = cardProduct();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    actingAs($user)->followingRedirects()->get(route('wishlist.index'))->assertInertia(fn (Assert $page) => $page
        ->where('wishlist.0.id', $product->id)
        ->missing('wishlist.0.description'));
});

test('visitors are not told any admin route', function (string $path) {
    expect($this->get($path)->getContent())
        ->not->toContain('admin.products')->not->toContain('admin/orders')->not->toContain('admin.dashboard');
})->with(['/', '/login', '/cart']);

test('admin pages still get every route', function () {
    expect($this->get(route('admin.login'))->assertOk()->getContent())->toContain('admin.login.submit')->toContain('home');

    actingAs(createAdmin(), 'admin');

    expect($this->get(route('admin.dashboard'))->assertOk()->getContent())->toContain('admin.products.index');
});

test('an open storefront tab moves into admin with a full page load', function () {
    actingAs(createAdmin(), 'admin');

    $this->get(route('home'), ['X-Inertia' => 'true', 'X-Inertia-Version' => 'any'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('admin.dashboard'));

    $this->get(route('home'))->assertRedirect(route('admin.dashboard'));
});

test('the menu carries a short description and no sub-category text, and the file cache can hold it', function () {
    $category = Category::factory()->create(['description' => str_repeat('long text ', 50), 'is_active' => true]);
    SubCategory::factory()->create(['category_id' => $category->id, 'description' => 'Unused text', 'is_active' => true]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('navCategories.0.description', fn (string $description): bool => mb_strlen($description) === 160)
        ->has('navCategories.0.sub_categories.0.slug')
        ->missing('navCategories.0.sub_categories.0.description'));
});

test('the shop filter carries names and counts, not whole records', function () {
    $category = Category::factory()->create(['meta_title' => 'Search title', 'is_active' => true]);
    SubCategory::factory()->create(['category_id' => $category->id, 'meta_title' => 'Search title', 'image' => '/storage/tile.webp', 'is_active' => true]);
    Brand::factory()->create(['description' => 'Brand story', 'is_active' => true]);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->has('categories.0.products_count')
        ->has('categories.0.sub_categories.0.products_count')
        // The category page's sub-category tiles show this picture.
        ->has('categories.0.sub_categories.0.image')
        ->has('brands.0.products_count')
        ->missing('categories.0.meta_title')
        ->missing('categories.0.created_at')
        ->missing('categories.0.sub_categories.0.meta_title')
        ->missing('brands.0.description'));
});
