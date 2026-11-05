<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Cart;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * HIGH-02: orders decrement variants, never the parent, so for a variant
 * product `products.stock_quantity` is only the sum from its last admin save.
 * Every fixture here gives the parent a deliberately stale value.
 * Searches use the SKU: on MySQL a name search is FULLTEXT, which can't see
 * rows uncommitted inside the test's transaction.
 *
 * @param  array<int, int>  $variantStock  The first variant is the default.
 * @param  array<string, mixed>  $attributes
 */
function productWithStock(int $parentStock, array $variantStock = [], array $attributes = []): Product
{
    $product = Product::factory()->create(array_merge([
        'name' => 'Stock Test '.Str::random(8),
        'stock_quantity' => $parentStock,
        'low_stock_threshold' => 5,
        'is_featured' => true,
        'published_at' => now()->subDay(),
    ], $attributes));

    foreach (array_values($variantStock) as $index => $stock) {
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => $stock,
            'is_default' => $index === 0,
        ]);
    }

    return $product;
}

/**
 * @return array<int, array<string, mixed>>
 */
function pageProducts(string $url, string $path): array
{
    return data_get(get($url)->assertOk()->viewData('page')['props'], $path);
}

/**
 * Every storefront surface that renders a ProductCard, as the URL and prop
 * path holding its products.
 *
 * @return array<string, Closure(Product): array{0: string, 1: string}>
 */
function storefrontSurfaces(): array
{
    return [
        'homepage' => fn (Product $product) => [route('home'), 'featuredProducts'],
        'product listing' => fn (Product $product) => [route('products.index'), 'products.data'],
        'search results' => fn (Product $product) => [route('search', ['q' => $product->sku]), 'products.data'],
        'brand page' => function (Product $product) {
            $brand = Brand::factory()->create();
            $product->update(['brand_id' => $brand->id]);

            return [route('brand.show', $brand), 'products.data'];
        },
        'flash sales page' => fn (Product $product) => [route('flash-sales.index'), 'featuredProducts'],
        'related products' => fn (Product $product) => [route('product.show', Product::factory()->create(['category_id' => $product->category_id, 'published_at' => now()->subDay()])), 'relatedProducts'],
    ];
}

test('a variant product reports the stock its variants actually hold', function (Closure $surface) {
    $product = productWithStock(parentStock: 50, variantStock: [3, 4]);
    [$url, $path] = $surface($product);

    $card = collect(pageProducts($url, $path))->firstWhere('id', $product->id);

    expect($card['available_stock'])->toBe(7);
    expect($card['variants'])->toHaveCount(2);
})->with(storefrontSurfaces());

test('a variant product with no stock left in any variant reports none', function (Closure $surface) {
    $product = productWithStock(parentStock: 50, variantStock: [0, 0]);
    [$url, $path] = $surface($product);

    expect(collect(pageProducts($url, $path))->firstWhere('id', $product->id)['available_stock'])->toBe(0);
})->with(storefrontSurfaces());

test('a variant product with a stale zero parent still reports its variants\' stock', function () {
    $product = productWithStock(parentStock: 0, variantStock: [2]);

    expect(collect(pageProducts(route('products.index'), 'products.data'))->firstWhere('id', $product->id)['available_stock'])->toBe(2);
});

test('a variant product with only some variants in stock stays available', function () {
    $product = productWithStock(parentStock: 50, variantStock: [0, 6]);

    $card = collect(pageProducts(route('products.index'), 'products.data'))->firstWhere('id', $product->id);

    expect($card['available_stock'])->toBe(6);
    expect(collect($card['variants'])->firstWhere('is_default', true)['stock_quantity'])->toBe(0);
});

test('a product without variants keeps using its own stock', function (Closure $surface) {
    $product = productWithStock(parentStock: 9);
    [$url, $path] = $surface($product);

    expect(collect(pageProducts($url, $path))->firstWhere('id', $product->id)['available_stock'])->toBe(9);
})->with(storefrontSurfaces());

/**
 * Every surface this product can appear on must agree on its price while a
 * flash sale is active — found via an owner report that the product card
 * and search still showed the regular price while the detail page (the
 * only surface that originally looked the sale up) showed the sale price.
 * Each of these controllers queries its products separately from the
 * active FlashSale's own item list, so a naive "mutate the product
 * instance the flash sale loaded" fix (HomeController's original
 * approach, still used for its own dedicated flash-sale carousel) silently
 * does nothing for a *different* instance of the same database row loaded
 * by a second query — exactly what every surface here is.
 */
test('a product on an active flash sale shows its sale price on every storefront surface', function (Closure $surface) {
    $product = Product::factory()->create([
        'price' => 2000,
        'is_featured' => true,
        'published_at' => now()->subDay(),
    ]);
    $flashSale = FlashSale::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 1000,
    ]);
    [$url, $path] = $surface($product);

    $card = collect(pageProducts($url, $path))->firstWhere('id', $product->id);

    expect($card)->not->toBeNull();
    expect($card['in_flash_sale'])->toBeTrue();
    expect((float) $card['flash_sale_price'])->toBe(1000.0);
    expect((float) $card['price'])->toBe(2000.0);
})->with(storefrontSurfaces());

test('the product page and wishlist report variant stock', function () {
    $user = User::factory()->create();
    $product = productWithStock(parentStock: 50, variantStock: [1, 1]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    expect(data_get(get(route('product.show', $product))->viewData('page')['props'], 'product.available_stock'))->toBe(2);

    $wishlisted = data_get(actingAs($user)->get(route('account.wishlist'))->viewData('page')['props'], 'wishlist.0');

    expect($wishlisted['available_stock'])->toBe(2);
    expect($wishlisted['variants'])->toHaveCount(2);
});

test('the in-stock filter follows variant stock, not the parent', function (string $routeName) {
    $soldOutVariants = productWithStock(parentStock: 50, variantStock: [0, 0], attributes: ['sku' => 'STOCKFILTER-1']);
    $partlyInStock = productWithStock(parentStock: 0, variantStock: [0, 4], attributes: ['sku' => 'STOCKFILTER-2']);
    $plainInStock = productWithStock(parentStock: 4, attributes: ['sku' => 'STOCKFILTER-3']);
    $plainSoldOut = productWithStock(parentStock: 0, attributes: ['sku' => 'STOCKFILTER-4']);

    $ids = collect(pageProducts(route($routeName, ['in_stock' => 1, 'q' => 'STOCKFILTER']), 'products.data'))->pluck('id');

    expect($ids)->toContain($partlyInStock->id, $plainInStock->id);
    expect($ids)->not->toContain($soldOutVariants->id);
    expect($ids)->not->toContain($plainSoldOut->id);
})->with(['products.index', 'search']);

test('an order lowers the stock customers see even though the parent is not touched', function () {
    $user = User::factory()->create();
    $product = productWithStock(parentStock: 2, variantStock: [2]);
    $variant = $product->variants()->firstOrFail();
    $product->update(['price' => $variant->price]);
    Cart::query()->where('user_id', $user->id)->delete();

    actingAs($user)->post(route('checkout.store'), [
        'name' => 'Stock Buyer',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]],
    ])->assertRedirect();

    expect($variant->refresh()->stock_quantity)->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(2);
    expect(collect(pageProducts(route('products.index'), 'products.data'))->firstWhere('id', $product->id)['available_stock'])->toBe(0);
});

test('reading available stock never queries when variants were not loaded', function () {
    $product = productWithStock(parentStock: 8, variantStock: [1]);
    $unloaded = Product::query()->findOrFail($product->id);

    DB::enableQueryLog();
    $stock = $unloaded->available_stock;

    expect($stock)->toBe(8);
    expect(DB::getQueryLog())->toBeEmpty();
});
