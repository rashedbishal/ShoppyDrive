<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
});

/** @return array<string, mixed> */
function cleanupProductPayload(array $overrides = []): array
{
    return array_merge([
        // Owner decision 2026-11-14: starting stock and stock changes need the admin's password.
        'stock_password' => 'password',
        'name' => 'Cleanup Product',
        'slug' => 'cleanup-product',
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'price' => 500,
        'stock_quantity' => 10,
        'is_active' => true,
    ], $overrides);
}

/** Creates the product through the admin form, so its photos are real uploads. */
function productWithUploadedPhotos(int $count = 3): Product
{
    test()->post(route('admin.products.store'), cleanupProductPayload([
        'images' => array_map(fn (int $i) => UploadedFile::fake()->image("photo{$i}.jpg"), range(1, $count)),
    ]))->assertSessionHasNoErrors();

    return Product::where('slug', 'cleanup-product')->firstOrFail();
}

/** @return list<string> */
function photoPaths(Product $product): array
{
    return $product->media()->orderBy('sort_order')->pluck('path')->all();
}

function fileExists(string $value): bool
{
    return Storage::disk('public')->exists(substr($value, strlen('/storage/')));
}

function updateProductMedia(Product $product, array $images): void
{
    test()->put(route('admin.products.update', $product), cleanupProductPayload([
        'images' => $images,
        'stock_token' => ProductStockToken::for($product->fresh()),
    ]))->assertSessionHasNoErrors();
}

test('a photo removed from a product leaves the disk; kept and new photos stay', function () {
    $product = productWithUploadedPhotos();
    [$first, $removed, $third] = photoPaths($product);

    updateProductMedia($product, [$first, $third, UploadedFile::fake()->image('new.jpg')]);

    $paths = photoPaths($product);
    expect(fileExists($removed))->toBeFalse()
        ->and(fileExists($first))->toBeTrue()
        ->and(fileExists($third))->toBeTrue()
        ->and(fileExists($paths[2]))->toBeTrue()
        ->and($paths)->toHaveCount(3);
});

test('saving a product without changing its photos keeps every file', function () {
    $product = productWithUploadedPhotos();
    $paths = photoPaths($product);

    updateProductMedia($product, $paths);

    expect(array_map(fileExists(...), $paths))->toBe([true, true, true]);
});

test('a removed photo an old order still shows is kept', function () {
    $product = productWithUploadedPhotos(2);
    [$ordered, $other] = photoPaths($product);
    $order = Order::create([
        'order_number' => 'ORD-CLEANUP-1', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Cleanup Product', 'sku' => 'CLEAN-1',
        'price' => 100, 'quantity' => 1, 'subtotal' => 100, 'product_snapshot' => ['image' => $ordered],
    ]);

    updateProductMedia($product, [UploadedFile::fake()->image('replacement.jpg')]);

    expect(fileExists($ordered))->toBeTrue()
        ->and(fileExists($other))->toBeFalse();
});

test('a removed photo another product also uses is kept', function () {
    $product = productWithUploadedPhotos(2);
    [$shared] = photoPaths($product);
    $other = Product::factory()->create();
    ProductMedia::create(['product_id' => $other->id, 'path' => $shared, 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);

    updateProductMedia($product, [UploadedFile::fake()->image('replacement.jpg')]);

    expect(fileExists($shared))->toBeTrue();
});

test('a product save that fails keeps the photos it would have dropped', function () {
    $product = productWithUploadedPhotos(2);
    $paths = photoPaths($product);
    ProductMedia::creating(fn () => throw new RuntimeException('Simulated failure.'));
    test()->withoutExceptionHandling();

    expect(fn () => test()->put(route('admin.products.update', $product), cleanupProductPayload([
        'images' => [UploadedFile::fake()->image('replacement.jpg')],
        'stock_token' => ProductStockToken::for($product->fresh()),
    ])))->toThrow(RuntimeException::class, 'Simulated failure.');

    expect(array_map(fileExists(...), $paths))->toBe([true, true])
        ->and(photoPaths($product))->toBe($paths);
});

test('permanently deleting a product removes its photo and video files', function () {
    $product = productWithUploadedPhotos(2);
    $paths = photoPaths($product);

    test()->delete(route('admin.products.destroy', $product))->assertSessionHasNoErrors();

    expect(Product::withTrashed()->find($product->id))->toBeNull()
        ->and(array_map(fileExists(...), $paths))->toBe([false, false]);
});

test('a product can no longer be saved with all of its photos removed', function () {
    $product = productWithUploadedPhotos(1);
    $before = photoPaths($product);

    test()->put(route('admin.products.update', $product), cleanupProductPayload([
        'images' => [],
        'stock_token' => ProductStockToken::for($product->fresh()),
    ]));

    expect(photoPaths($product))->toBe($before);
});
