<?php

use App\Enums\AttributeType;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);

    $colorAttribute = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $this->red = AttributeValue::create(['attribute_id' => $colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    $this->blue = AttributeValue::create(['attribute_id' => $colorAttribute->id, 'value' => 'Blue', 'color_hex' => '#0000ff']);

    $sizeAttribute = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $this->sizeM = AttributeValue::create(['attribute_id' => $sizeAttribute->id, 'value' => 'M']);
    $this->sizeXl = AttributeValue::create(['attribute_id' => $sizeAttribute->id, 'value' => 'XL']);
});

function baseProductPayload(array $overrides = []): array
{
    $payload = array_merge([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'category_id' => null,
        'sub_category_id' => null,
        'price' => '1000',
        'stock_quantity' => '10',
        'is_active' => true,
    ], $overrides);

    // A null override means "omit this key" (e.g. price/stock_quantity when
    // variants supply them instead) rather than literally submitting null.
    return array_filter($payload, fn ($value) => $value !== null);
}

test('admin can create a flat product without variants (regression)', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect($product)->not->toBeNull();
    expect((float) $product->price)->toBe(1000.0);
    expect($product->stock_quantity)->toBe(10);
    expect($product->has_color_variant)->toBeFalse();
    expect($product->variants)->toHaveCount(0);
    expect($product->media)->toHaveCount(1);
});

test('creating a product with a name/slug already in use returns a slug validation error', function () {
    actingAs($this->admin, 'admin');

    Product::create([
        'name' => 'Existing Product',
        'slug' => 'existing-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => 1000,
        'stock_quantity' => 10,
    ]);

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'name' => 'Existing Product',
        'slug' => 'existing-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
    ]));

    $response->assertInvalid(['slug']);
    expect(Product::where('slug', 'existing-product')->count())->toBe(1);
});

test('creating a variant product with empty price/stock_quantity strings does not fail validation (regression)', function () {
    // The create-product form hides the flat price/stock inputs once
    // variants are enabled, but still submits them as empty strings rather
    // than omitting the keys — Laravel's ConvertEmptyStringsToNull
    // middleware turns those into null before validation runs.
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => '',
        'stock_quantity' => '',
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));
    expect(Product::where('slug', 'test-product')->exists())->toBeTrue();
});

test('creating a variant product with string-typed variant ids does not throw (regression)', function () {
    // The browser sends multipart/form-data instead of JSON whenever a product
    // image is attached, which stringifies every field — including
    // variants.*.color_value_id/size_value_id. The "integer" validation rule
    // only checks that the string looks numeric, it doesn't cast it, so the
    // controller must not assume these values arrive as PHP ints.
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => (string) $this->red->id, 'price' => '1200', 'stock_quantity' => '5', 'is_default' => true],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect($product->variants)->toHaveCount(1);
    expect($product->variants->first()->color_value_id)->toBe($this->red->id);
});

test('admin can create a product with color-only variants and price/stock are denormalized', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1200, 'compare_price' => 1500, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'price' => 1100, 'stock_quantity' => 7, 'is_default' => false],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect((float) $product->price)->toBe(1100.0); // min of variant prices
    expect((float) $product->compare_price)->toBe(1500.0); // default variant's compare_price
    expect($product->stock_quantity)->toBe(12); // sum of variant stock
    expect($product->variants)->toHaveCount(2);
    expect($product->attributeValues()->pluck('attribute_values.id')->sort()->values()->all())
        ->toBe(collect([$this->red->id, $this->blue->id])->sort()->values()->all());

    $redVariant = ProductVariant::where('product_id', $product->id)->where('color_value_id', $this->red->id)->first();
    expect($redVariant->is_default)->toBeTrue();
    expect($redVariant->attributes)->toBe(['Color' => 'Red']);
});

test('duplicate color pair in the same submission is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->red->id, 'price' => 1300, 'stock_quantity' => 3, 'is_default' => false],
        ],
    ]));

    $response->assertInvalid();
    expect(Product::where('slug', 'test-product')->exists())->toBeFalse();
});

test('duplicate SKU within the same submission is rejected', function () {
    // DB-001/CAT-001 regression: no application-level guard previously
    // stopped two variants in the same request from sharing a SKU.
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'sku' => 'DUP-SKU', 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'sku' => 'DUP-SKU', 'price' => 1300, 'stock_quantity' => 3, 'is_default' => false],
        ],
    ]));

    $response->assertInvalid();
    expect(Product::where('slug', 'test-product')->exists())->toBeFalse();
});

test('case-only SKU difference within the same submission is still rejected', function () {
    // product_variants.sku's actual column collation is
    // utf8mb4_unicode_ci (case-insensitive) — "dup-sku" and "DUP-SKU"
    // are the same value as far as MySQL is concerned, so the
    // within-submission check must fold case too, not just do an exact
    // PHP string comparison.
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'sku' => 'dup-sku', 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'sku' => 'DUP-SKU', 'price' => 1300, 'stock_quantity' => 3, 'is_default' => false],
        ],
    ]));

    $response->assertInvalid();
    expect(Product::where('slug', 'test-product')->exists())->toBeFalse();
});

test('a SKU already used by a variant on a different product is rejected case-insensitively', function () {
    actingAs($this->admin, 'admin');

    $otherProduct = Product::factory()->create(['category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id]);
    ProductVariant::create([
        'product_id' => $otherProduct->id, 'sku' => 'taken-sku', 'price' => 500, 'stock_quantity' => 5,
        'attributes' => [], 'is_default' => true,
    ]);

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            // Differs only by case from the other product's "taken-sku".
            ['color_value_id' => $this->red->id, 'sku' => 'TAKEN-SKU', 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
        ],
    ]));

    $response->assertInvalid();
    expect(Product::where('slug', 'test-product')->exists())->toBeFalse();
});

test('multiple blank SKUs in the same submission are not flagged as duplicates and are persisted as null', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'sku' => '', 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'sku' => '   ', 'price' => 1300, 'stock_quantity' => 3, 'is_default' => false],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect($product->variants)->toHaveCount(2);
    foreach ($product->variants as $variant) {
        expect($variant->sku)->toBeNull();
    }
});

test('explicit null SKUs alongside ordinary distinct SKUs are all accepted', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'has_size_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'size_value_id' => $this->sizeM->id, 'sku' => null, 'price' => 500, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'size_value_id' => $this->sizeM->id, 'sku' => 'DISTINCT-ONE', 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
            ['color_value_id' => $this->blue->id, 'size_value_id' => $this->sizeXl->id, 'sku' => 'DISTINCT-TWO', 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect($product->variants)->toHaveCount(3);
    $skus = $product->variants->pluck('sku')->all();
    expect($skus)->toContain(null, 'DISTINCT-ONE', 'DISTINCT-TWO');
});

test('a SKU already used by a variant on a different product is rejected', function () {
    actingAs($this->admin, 'admin');

    $otherProduct = Product::factory()->create(['category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id]);
    ProductVariant::create([
        'product_id' => $otherProduct->id,
        'sku' => 'TAKEN-SKU',
        'price' => 500,
        'stock_quantity' => 5,
        'attributes' => [],
        'is_default' => true,
    ]);

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'sku' => 'TAKEN-SKU', 'price' => 1200, 'stock_quantity' => 5, 'is_default' => true],
        ],
    ]));

    $response->assertInvalid();
    expect(Product::where('slug', 'test-product')->exists())->toBeFalse();
});

test('a product can resubmit its own existing variant SKU without self-conflicting', function () {
    actingAs($this->admin, 'admin');

    $product = Product::create([
        'name' => 'Self Sku Product', 'slug' => 'self-sku-product',
        'category_id' => $this->category->id, 'sub_category_id' => $this->subCategory->id,
        'price' => 1000, 'stock_quantity' => 10, 'has_color_variant' => true,
    ]);
    ProductVariant::create([
        'product_id' => $product->id, 'color_value_id' => $this->red->id,
        'sku' => 'KEEP-MINE', 'price' => 1000, 'stock_quantity' => 5,
        'attributes' => ['Color' => 'Red'], 'is_default' => true,
    ]);

    $response = $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'slug' => 'self-sku-product',
        'variants' => [
            ['color_value_id' => $this->red->id, 'sku' => 'KEEP-MINE', 'price' => 1100, 'stock_quantity' => 6, 'is_default' => true],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));
    expect(ProductVariant::where('product_id', $product->id)->first()->price)->toEqual(1100);
});

test('exactly one default variant is required', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1200, 'stock_quantity' => 5, 'is_default' => false],
            ['color_value_id' => $this->blue->id, 'price' => 1100, 'stock_quantity' => 7, 'is_default' => false],
        ],
    ]));

    $response->assertInvalid(['variants']);
});

test('removing a variant combo referenced by a live cart item freezes it instead of deleting', function () {
    actingAs($this->admin, 'admin');

    $product = Product::create([
        'name' => 'Frozen Variant Product',
        'slug' => 'frozen-variant-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => 1000,
        'stock_quantity' => 10,
        'has_color_variant' => true,
    ]);

    $redVariant = ProductVariant::create([
        'product_id' => $product->id,
        'color_value_id' => $this->red->id,
        'price' => 1000,
        'stock_quantity' => 5,
        'attributes' => ['Color' => 'Red'],
        'is_default' => true,
    ]);

    $blueVariant = ProductVariant::create([
        'product_id' => $product->id,
        'color_value_id' => $this->blue->id,
        'price' => 1000,
        'stock_quantity' => 5,
        'attributes' => ['Color' => 'Blue'],
        'is_default' => false,
    ]);

    $product->attributeValues()->sync([$this->red->id, $this->blue->id]);

    $cart = Cart::create(['session_id' => 'test-session']);
    $cartItem = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $blueVariant->id,
        'quantity' => 1,
        'price' => 1000,
    ]);

    // Admin edits the product, keeping only the Red variant (removing Blue).
    $response = $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'slug' => 'frozen-variant-product',
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1000, 'stock_quantity' => 5, 'is_default' => true],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    // The cart item and its referenced variant must survive untouched.
    expect(CartItem::find($cartItem->id))->not->toBeNull();
    expect(ProductVariant::find($blueVariant->id))->not->toBeNull();

    // But Blue is no longer a selectable attribute for this product.
    expect($product->attributeValues()->pluck('attribute_values.id')->all())->toBe([$this->red->id]);
});

test('turning off a size variant that has an order-frozen combo detaches it from the selectable attributes (regression)', function () {
    actingAs($this->admin, 'admin');

    $product = Product::create([
        'name' => 'Korra Product',
        'slug' => 'korra-product',
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'price' => 1000,
        'stock_quantity' => 10,
        'has_color_variant' => true,
        'has_size_variant' => true,
    ]);

    $blueXl = ProductVariant::create([
        'product_id' => $product->id,
        'color_value_id' => $this->blue->id,
        'size_value_id' => $this->sizeXl->id,
        'price' => 1000,
        'stock_quantity' => 5,
        'attributes' => ['Color' => 'Blue', 'Size' => 'XL'],
        'is_default' => true,
    ]);

    $product->attributeValues()->sync([$this->blue->id, $this->sizeXl->id]);

    $order = Order::create([
        'order_number' => 'ORD-KORRA-'.$product->id,
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 1000,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 1000,
        'shipping_address' => [],
        'billing_address' => [],
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => $blueXl->id,
        'product_name' => $product->name,
        'price' => 1000,
        'quantity' => 1,
        'subtotal' => 1000,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    // Admin turns the "Has Size Variant" toggle off, leaving only a blue, size-less variant.
    $response = $this->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'has_size_variant' => false,
        'price' => null,
        'stock_quantity' => null,
        'slug' => 'korra-product',
        'variants' => [
            // A stale row still carrying size_value_id, as a desynced variant
            // manager could submit — the backend must ignore it regardless.
            ['color_value_id' => $this->blue->id, 'size_value_id' => $this->sizeXl->id, 'price' => 1000, 'stock_quantity' => 5, 'is_default' => true],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    // The order and its frozen variant survive untouched.
    expect(ProductVariant::find($blueXl->id))->not->toBeNull();

    // But Size/XL is no longer a selectable attribute for this product.
    expect($product->attributeValues()->pluck('attribute_values.id')->all())->toBe([$this->blue->id]);

    $newVariant = $product->variants()->whereNull('size_value_id')->first();
    expect($newVariant)->not->toBeNull();
    expect($newVariant->attributes)->not->toHaveKey('Size');
});

test('media can be tagged to a specific color variant', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1000, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'price' => 1000, 'stock_quantity' => 5, 'is_default' => false],
        ],
        'images' => ['/storage/products/red.jpg', '/storage/products/blue.jpg', '/storage/products/general.jpg'],
        'media_tags' => ["color:{$this->red->id}", "color:{$this->blue->id}", null],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    $redVariant = ProductVariant::where('product_id', $product->id)->where('color_value_id', $this->red->id)->first();
    $blueVariant = ProductVariant::where('product_id', $product->id)->where('color_value_id', $this->blue->id)->first();

    $redMedia = $product->media()->where('path', '/storage/products/red.jpg')->first();
    $blueMedia = $product->media()->where('path', '/storage/products/blue.jpg')->first();
    $generalMedia = $product->media()->where('path', '/storage/products/general.jpg')->first();

    expect($redMedia->variant_id)->toBe($redVariant->id);
    expect($blueMedia->variant_id)->toBe($blueVariant->id);
    expect($generalMedia->variant_id)->toBeNull();
});

/** A Red/Blue × M/XL product (no Blue/XL), saved with the given media tags. */
function saveColorSizeProduct(array $images, array $mediaTags): Product
{
    test()->post(route('admin.products.store'), baseProductPayload([
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'has_color_variant' => true,
        'has_size_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => test()->red->id, 'size_value_id' => test()->sizeM->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => test()->red->id, 'size_value_id' => test()->sizeXl->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
            ['color_value_id' => test()->blue->id, 'size_value_id' => test()->sizeM->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
        ],
        'images' => $images,
        'media_tags' => $mediaTags,
    ]))->assertRedirect(route('admin.products.index'));

    return Product::where('slug', 'test-product')->firstOrFail();
}

test('on a color and size product, one image chosen for a color serves every size of it', function () {
    actingAs($this->admin, 'admin');

    $product = saveColorSizeProduct(['/storage/products/red.jpg', '/storage/products/general.jpg'], ["color:{$this->red->id}", null]);

    $redMedia = $product->media()->where('path', '/storage/products/red.jpg')->firstOrFail();
    expect($redMedia->color_value_id)->toBe($this->red->id);
    expect($redMedia->variant_id)->toBeNull();

    $images = ProductVariant::where('product_id', $product->id)->get()
        ->mapWithKeys(fn (ProductVariant $v) => ["{$v->attributes['Color']}/{$v->attributes['Size']}" => $v->image]);
    expect($images->all())->toBe([
        'Red/M' => '/storage/products/red.jpg',
        'Red/XL' => '/storage/products/red.jpg',
        'Blue/M' => null,
    ]);
});

test('a color/size pair tag from a form opened before the change counts as its color', function () {
    actingAs($this->admin, 'admin');

    $product = saveColorSizeProduct(['/storage/products/red.jpg'], ["pair:{$this->red->id}-{$this->sizeM->id}"]);

    $media = $product->media()->firstOrFail();
    expect($media->color_value_id)->toBe($this->red->id);
    expect($media->variant_id)->toBeNull();
    expect(ProductVariant::where('product_id', $product->id)->where('size_value_id', $this->sizeXl->id)->firstOrFail()->image)
        ->toBe('/storage/products/red.jpg');
});

test('a color tag for a color the product has no variant of is ignored', function () {
    actingAs($this->admin, 'admin');
    $green = AttributeValue::create(['attribute_id' => $this->red->attribute_id, 'value' => 'Green', 'color_hex' => '#00ff00']);

    $product = saveColorSizeProduct(['/storage/products/green.jpg'], ["color:{$green->id}"]);

    $media = $product->media()->firstOrFail();
    expect($media->color_value_id)->toBeNull();
    expect($media->variant_id)->toBeNull();
});

test('a color tag on a color-only product records the color as well as its variant', function () {
    actingAs($this->admin, 'admin');

    $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->red->id, 'price' => 1000, 'stock_quantity' => 5, 'is_default' => true],
        ],
        'images' => ['/storage/products/red.jpg'],
        'media_tags' => ["color:{$this->red->id}"],
    ]))->assertRedirect(route('admin.products.index'));

    $media = Product::where('slug', 'test-product')->firstOrFail()->media()->firstOrFail();
    expect($media->color_value_id)->toBe($this->red->id);
    expect($media->variant_id)->toBe(ProductVariant::where('color_value_id', $this->red->id)->value('id'));
});

test('admin can submit a partial set of color/size pairs, skipping unwanted combinations', function () {
    actingAs($this->admin, 'admin');

    // Blue/M, Blue/XL, Red/M are wanted — Red/XL is deliberately not submitted.
    $response = $this->post(route('admin.products.store'), baseProductPayload([
        'category_id' => $this->category->id,
        'sub_category_id' => $this->subCategory->id,
        'has_color_variant' => true,
        'has_size_variant' => true,
        'price' => null,
        'stock_quantity' => null,
        'variants' => [
            ['color_value_id' => $this->blue->id, 'size_value_id' => $this->sizeM->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => true],
            ['color_value_id' => $this->blue->id, 'size_value_id' => $this->sizeXl->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
            ['color_value_id' => $this->red->id, 'size_value_id' => $this->sizeM->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => false],
        ],
    ]));

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'test-product')->first();
    expect($product->variants)->toHaveCount(3);

    $pairs = $product->variants->map(fn (ProductVariant $v) => [$v->color_value_id, $v->size_value_id])->all();
    expect($pairs)->toContain([$this->blue->id, $this->sizeM->id]);
    expect($pairs)->toContain([$this->blue->id, $this->sizeXl->id]);
    expect($pairs)->toContain([$this->red->id, $this->sizeM->id]);
    expect($pairs)->not->toContain([$this->red->id, $this->sizeXl->id]);
});
