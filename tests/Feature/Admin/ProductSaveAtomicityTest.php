<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    actingAs(createAdmin(['name' => 'Catalog Admin', 'email' => 'catalog@example.com', 'password' => bcrypt('password')]), 'admin');

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);

    $color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $this->red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
    $this->blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue']);

    $this->withoutExceptionHandling();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function atomicProductPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Atomic Shirt',
        'slug' => 'atomic-shirt',
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'price' => 500,
        'stock_quantity' => 10,
        'has_color_variant' => true,
        'has_size_variant' => false,
        'is_active' => true,
        'variants' => [
            ['color_value_id' => test()->red->id, 'price' => 500, 'stock_quantity' => 3, 'is_default' => true, 'sku' => 'RED-1'],
            ['color_value_id' => test()->blue->id, 'price' => 550, 'stock_quantity' => 4, 'is_default' => false, 'sku' => 'BLUE-1'],
        ],
    ], $overrides);
}

function failOn(string $model, string $event = 'creating'): void
{
    $model::$event(fn () => throw new RuntimeException('Simulated failure.'));
}

/**
 * @return array{0: Product, 1: string}
 */
function existingProductWithMedia(): array
{
    Storage::disk('public')->put('products/old.jpg', 'old-image');

    $product = Product::factory()->create([
        'name' => 'Original Shirt', 'category_id' => test()->category->id, 'price' => 100, 'stock_quantity' => 7, 'has_color_variant' => true,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'color_value_id' => test()->red->id, 'price' => 100, 'stock_quantity' => 7, 'sku' => 'ORIGINAL-1',
    ]);
    $product->attributeValues()->sync([test()->red->id]);
    $product->media()->create(['path' => '/storage/products/old.jpg', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0, 'variant_id' => $variant->id]);

    return [$product, '/storage/products/old.jpg'];
}

function expectOriginalProductIntact(Product $product, string $oldPath): void
{
    $product = Product::with(['variants', 'media', 'attributeValues'])->findOrFail($product->id);

    expect($product->name)->toBe('Original Shirt');
    expect((int) $product->stock_quantity)->toBe(7);
    expect($product->variants)->toHaveCount(1);
    expect($product->variants->first()->sku)->toBe('ORIGINAL-1');
    expect((int) $product->variants->first()->stock_quantity)->toBe(7);
    expect($product->attributeValues->pluck('id')->all())->toBe([test()->red->id]);
    expect($product->media->pluck('path')->all())->toBe([$oldPath]);

    Storage::disk('public')->assertExists('products/old.jpg');
    expect(Storage::disk('public')->allFiles('products'))->toBe(['products/old.jpg']);
}

test('a product is created with its variants, media and attribute values', function () {
    $this->post(route('admin.products.store'), atomicProductPayload([
        'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
        'media_tags' => ['color:'.$this->blue->id, null],
    ]))->assertRedirect(route('admin.products.index'));

    $product = Product::with(['variants', 'media', 'attributeValues'])->firstOrFail();

    expect($product->variants)->toHaveCount(2);
    expect((int) $product->stock_quantity)->toBe(7);
    expect($product->attributeValues)->toHaveCount(2);
    expect($product->media)->toHaveCount(2);
    expect($product->media->first()->is_primary)->toBeTrue();
    expect($product->media->first()->variant_id)->toBe($product->variants->firstWhere('color_value_id', $this->blue->id)->id);

    foreach ($product->media as $media) {
        Storage::disk('public')->assertExists(substr($media->path, strlen('/storage/')));
    }
});

test('a video upload keeps its video type', function () {
    $this->post(route('admin.products.store'), atomicProductPayload([
        'images' => [UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4')],
    ]))->assertRedirect();

    expect(ProductMedia::query()->firstOrFail()->type)->toBe('video');
});

test('a failed create leaves no product, rows, audit entry or uploaded files', function (string $model) {
    failOn($model);

    expect(fn () => $this->post(route('admin.products.store'), atomicProductPayload([
        'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
    ])))->toThrow(RuntimeException::class, 'Simulated failure.');

    expect(Product::query()->count())->toBe(0);
    expect(ProductVariant::query()->count())->toBe(0);
    expect(ProductMedia::query()->count())->toBe(0);
    expect(Activity::query()->where('subject_type', Product::class)->count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['during variants' => [ProductVariant::class], 'during media' => [ProductMedia::class]]);

test('a create failing on its final media row removes every upload', function () {
    $created = 0;
    ProductMedia::creating(function () use (&$created): void {
        if (++$created === 2) {
            throw new RuntimeException('Simulated failure.');
        }
    });

    expect(fn () => $this->post(route('admin.products.store'), atomicProductPayload([
        'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
    ])))->toThrow(RuntimeException::class);

    expect(Product::query()->count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a failed update keeps the previous data and files, and removes new uploads', function (string $model, string $event) {
    [$product, $oldPath] = existingProductWithMedia();
    failOn($model, $event);

    expect(fn () => $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload([
        'name' => 'Changed Shirt',
        'images' => [UploadedFile::fake()->image('new.jpg')],
    ])))->toThrow(RuntimeException::class, 'Simulated failure.');

    expectOriginalProductIntact($product, $oldPath);
})->with([
    'creating a variant' => [ProductVariant::class, 'creating'],
    'updating a variant' => [ProductVariant::class, 'updating'],
    'creating media' => [ProductMedia::class, 'creating'],
]);

test('a successful update replaces media rows and keeps the old file for order history', function () {
    [$product, $oldPath] = existingProductWithMedia();
    $order = Order::create([
        'order_number' => 'ORD-ATOMIC-1', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Original Shirt', 'sku' => 'ORIGINAL-1',
        'price' => 100, 'quantity' => 1, 'subtotal' => 100, 'product_snapshot' => ['image' => $oldPath],
    ]);

    $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload([
        'name' => 'Changed Shirt',
        'images' => [UploadedFile::fake()->image('new.jpg')],
    ]))->assertRedirect(route('admin.products.index'));

    $media = $product->refresh()->media;

    expect($product->name)->toBe('Changed Shirt');
    expect($media)->toHaveCount(1);
    expect($media->first()->path)->not->toBe($oldPath);
    Storage::disk('public')->assertExists(substr($media->first()->path, strlen('/storage/')));
    Storage::disk('public')->assertExists('products/old.jpg');
});

test('an update keeping its existing media keeps the same path', function () {
    [$product, $oldPath] = existingProductWithMedia();

    $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload(['images' => [$oldPath]]))->assertRedirect();

    expect($product->media()->pluck('path')->all())->toBe([$oldPath]);
    expect(Storage::disk('public')->allFiles('products'))->toBe(['products/old.jpg']);
});

test('an update that sends no images leaves media alone', function () {
    [$product, $oldPath] = existingProductWithMedia();

    $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload())->assertRedirect();

    expect($product->media()->pluck('path')->all())->toBe([$oldPath]);
});

/**
 * MEDIA-VIDEO-TYPE-001: a retained (not re-uploaded) media path arrives with
 * no type info of its own — storeUploads() must look its real type up by
 * path instead of defaulting it to 'image'.
 */
test('an update keeping its existing video media keeps its video type', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'has_color_variant' => true]);
    $product->media()->create(['path' => '/storage/products/clip.mp4', 'type' => 'video', 'is_primary' => true, 'sort_order' => 0]);

    $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload([
        'images' => ['/storage/products/clip.mp4'],
    ]))->assertRedirect();

    expect(ProductMedia::where('product_id', $product->id)->firstOrFail()->type)->toBe('video');
});

test('an update keeping its existing image media keeps its image type', function () {
    [$product, $oldPath] = existingProductWithMedia();

    $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + atomicProductPayload(['images' => [$oldPath]]))->assertRedirect();

    expect(ProductMedia::where('product_id', $product->id)->firstOrFail()->type)->toBe('image');
});
