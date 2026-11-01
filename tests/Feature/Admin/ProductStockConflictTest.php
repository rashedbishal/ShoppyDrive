<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Services\InventoryService;
use App\Support\ProductStockToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    actingAs(createAdmin(['name' => 'Stock Admin', 'email' => 'stock@example.com', 'password' => bcrypt('password')]), 'admin');

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
    $color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $this->red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']);
    $this->blue = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue']);
});

function flatStockProduct(int $stock = 10): Product
{
    return Product::factory()->create([
        'name' => 'Flat Original', 'slug' => 'flat-original', 'price' => 100, 'stock_quantity' => $stock,
        'category_id' => test()->category->id, 'sub_category_id' => test()->subCategory->id, 'is_active' => true,
    ]);
}

function variantStockProduct(): Product
{
    $product = flatStockProduct(10);
    $product->update(['has_color_variant' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => test()->red->id, 'stock_quantity' => 6, 'price' => 100, 'sku' => 'STALE-RED', 'is_default' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => test()->blue->id, 'stock_quantity' => 4, 'price' => 100, 'sku' => 'STALE-BLUE', 'is_default' => false]);

    return $product;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function flatSavePayload(Product $product, string $token, array $overrides = []): array
{
    return array_merge([
        'stock_token' => $token,
        'name' => 'Changed Name',
        'slug' => $product->slug,
        'category_id' => $product->category_id,
        'sub_category_id' => $product->sub_category_id,
        'price' => 250,
        'stock_quantity' => 99,
        'is_active' => true,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantSavePayload(Product $product, string $token, array $overrides = []): array
{
    return flatSavePayload($product, $token, array_merge([
        'has_color_variant' => true,
        'variants' => [
            ['color_value_id' => test()->red->id, 'price' => 300, 'stock_quantity' => 50, 'is_default' => true, 'sku' => 'STALE-RED'],
            ['color_value_id' => test()->blue->id, 'price' => 300, 'stock_quantity' => 60, 'is_default' => false, 'sku' => 'STALE-BLUE'],
        ],
    ], $overrides));
}

function sellFromOrder(Product $product, ?ProductVariant $variant, int $quantity): Order
{
    $order = Order::create([
        'order_number' => 'ORD-STOCK-'.uniqid(), 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);

    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'variant_id' => $variant?->id, 'product_name' => $product->name,
        'sku' => 'SKU', 'price' => 100, 'quantity' => $quantity, 'subtotal' => 100 * $quantity, 'product_snapshot' => [],
    ]);

    app(InventoryService::class)->decrementForOrderItem($order, $product->id, $variant?->id, $quantity, 100.0);

    return $order;
}

test('the edit page gives the form a token for the current stock', function () {
    $product = variantStockProduct();

    $this->get(route('admin.products.edit', $product))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stockToken', ProductStockToken::for($product)));
});

test('an unchanged form saves new stock, as before', function () {
    $product = flatStockProduct();
    $logs = InventoryLog::query()->count();

    $this->put(route('admin.products.update', $product), flatSavePayload($product, ProductStockToken::for($product)))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    expect($product->refresh()->stock_quantity)->toBe(99);
    expect($product->name)->toBe('Changed Name');
    expect(InventoryLog::query()->count())->toBe($logs);
    expect(Activity::query()->where('subject_type', Product::class)->where('subject_id', $product->id)->where('event', 'updated')->count())->toBe(1);
});

test('an unchanged form saves new variant stock', function () {
    $product = variantStockProduct();

    $this->put(route('admin.products.update', $product), variantSavePayload($product, ProductStockToken::for($product)))->assertSessionHasNoErrors();

    expect($product->variants()->orderBy('id')->pluck('stock_quantity')->all())->toBe([50, 60]);
    expect($product->refresh()->stock_quantity)->toBe(110);
});

test('a save is refused after an order changed product stock', function () {
    $product = flatStockProduct();
    $token = ProductStockToken::for($product);
    sellFromOrder($product, null, 3);
    $logs = InventoryLog::query()->count();

    $this->put(route('admin.products.update', $product), flatSavePayload($product, $token))->assertSessionHasErrors('stock_token');

    expect($product->refresh()->stock_quantity)->toBe(7);
    expect($product->name)->toBe('Flat Original');
    expect((float) $product->price)->toBe(100.0);
    expect(InventoryLog::query()->count())->toBe($logs);
});

test('a save is refused when one of several variants changed, and leaves everything alone', function () {
    $product = variantStockProduct();
    $product->media()->create(['path' => '/storage/products/old.jpg', 'type' => 'image', 'is_primary' => true, 'sort_order' => 0]);
    $token = ProductStockToken::for($product);
    $red = $product->variants()->where('color_value_id', $this->red->id)->firstOrFail();
    sellFromOrder($product, $red, 2);
    $logs = InventoryLog::query()->count();
    $activities = Activity::query()->count();

    $this->put(route('admin.products.update', $product), variantSavePayload($product, $token, [
        'images' => [UploadedFile::fake()->image('new.jpg')],
    ]))->assertSessionHasErrors('stock_token');

    expect($product->variants()->orderBy('id')->pluck('stock_quantity')->all())->toBe([4, 4]);
    expect($product->variants()->orderBy('id')->pluck('price')->map(fn ($price) => (float) $price)->all())->toBe([100.0, 100.0]);
    expect($product->refresh()->name)->toBe('Flat Original');
    expect((int) $product->stock_quantity)->toBe(10);
    expect(ProductMedia::query()->pluck('path')->all())->toBe(['/storage/products/old.jpg']);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    expect(InventoryLog::query()->count())->toBe($logs);
    expect(Activity::query()->count())->toBe($activities);
});

test('a cancellation that restored stock also makes the form stale', function () {
    $product = flatStockProduct();
    $order = sellFromOrder($product, null, 3);
    $token = ProductStockToken::for($product);

    $order->update(['status' => 'cancelled']);

    expect($product->refresh()->stock_quantity)->toBe(10);

    $this->put(route('admin.products.update', $product), flatSavePayload($product, $token))->assertSessionHasErrors('stock_token');

    expect($product->refresh()->stock_quantity)->toBe(10);
});

test('the second of two admin sessions is refused after the first changes stock', function () {
    $product = flatStockProduct();
    $sessionA = ProductStockToken::for($product);
    $sessionB = ProductStockToken::for($product);

    $this->put(route('admin.products.update', $product), flatSavePayload($product, $sessionA, ['stock_quantity' => 20]))->assertSessionHasNoErrors();
    $this->put(route('admin.products.update', $product), flatSavePayload($product, $sessionB, ['stock_quantity' => 30, 'name' => 'Session B']))->assertSessionHasErrors('stock_token');

    expect($product->refresh()->stock_quantity)->toBe(20);
    expect($product->name)->toBe('Changed Name');
});

test('a resubmitted stale form stays refused until it is reloaded', function () {
    $product = flatStockProduct();
    $token = ProductStockToken::for($product);
    sellFromOrder($product, null, 1);

    foreach ([1, 2] as $attempt) {
        $this->put(route('admin.products.update', $product), flatSavePayload($product, $token))->assertSessionHasErrors('stock_token');
    }

    $this->put(route('admin.products.update', $product), flatSavePayload($product, ProductStockToken::for($product), ['stock_quantity' => 9]))->assertSessionHasNoErrors();

    expect($product->refresh()->stock_quantity)->toBe(9);
});

test('a save without a valid token is refused', function (mixed $token) {
    $product = flatStockProduct();

    $this->put(route('admin.products.update', $product), flatSavePayload($product, 'placeholder', ['stock_token' => $token]))->assertSessionHasErrors('stock_token');

    expect($product->refresh()->stock_quantity)->toBe(10);
})->with(['missing' => [null], 'forged' => [str_repeat('a', 64)]]);

test('a token from another product or an unkeyed hash is refused', function () {
    $product = flatStockProduct();
    $other = Product::factory()->create(['stock_quantity' => 10]);

    foreach ([ProductStockToken::for($other), hash('sha256', json_encode(['product-stock', $product->id, 10, []]))] as $token) {
        $this->put(route('admin.products.update', $product), flatSavePayload($product, $token))->assertSessionHasErrors('stock_token');
    }

    expect($product->refresh()->stock_quantity)->toBe(10);
});

test('a client cannot pass a token when creating a product', function () {
    $product = flatStockProduct();

    $this->post(route('admin.products.store'), flatSavePayload($product, 'anything', ['slug' => 'brand-new']))->assertSessionHasErrors('stock_token');
});
