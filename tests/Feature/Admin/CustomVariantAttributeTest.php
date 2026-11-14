<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Models\User;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Variants built from any attributes, not only Color and Size: a spoon in
 * sets of 6pcs or 12pcs, or a shirt in Red/Blue × 6pcs/12pcs. Each variant
 * records its values in the product's attribute order, its labels, and the
 * storefront, cart and order history show them.
 */
beforeEach(function () {
    $this->admin = createAdmin();
    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);

    $this->set = Attribute::create(['name' => 'Set', 'type' => AttributeType::SELECT]);
    $this->six = AttributeValue::create(['attribute_id' => $this->set->id, 'value' => '6pcs']);
    $this->twelve = AttributeValue::create(['attribute_id' => $this->set->id, 'value' => '12pcs']);

    $this->color = Attribute::firstWhere('name', 'Color');
    $this->red = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    $this->blue = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Blue', 'color_hex' => '#0000ff']);
});

function customVariantRow(array $valueIds, array $overrides = []): array
{
    return ['value_ids' => $valueIds, 'price' => 500, 'stock_quantity' => 5, 'is_default' => false, ...$overrides];
}

function customVariantPayload(array $attributeIds, array $variants, array $overrides = []): array
{
    return [
        // Owner decision 2026-11-14: starting stock and stock changes need the admin's password.
        'stock_password' => 'password',
        'name' => 'Steel Spoon', 'slug' => 'steel-spoon', 'is_active' => true,
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'images' => [UploadedFile::fake()->image('product.jpg', 400, 400)],
        'category_id' => test()->category->id, 'sub_category_id' => test()->subCategory->id,
        'variant_attribute_ids' => $attributeIds,
        'variants' => $variants,
        ...$overrides,
    ];
}

function saveCustomVariantProduct(array $attributeIds, array $variants, array $overrides = []): Product
{
    actingAs(test()->admin, 'admin')
        ->post(route('admin.products.store'), customVariantPayload($attributeIds, $variants, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    return Product::where('slug', 'steel-spoon')->firstOrFail();
}

test('a product can have variants from a custom attribute alone', function () {
    $product = saveCustomVariantProduct([$this->set->id], [
        customVariantRow([$this->six->id], ['is_default' => true, 'price' => 300]),
        customVariantRow([$this->twelve->id], ['price' => 550]),
    ]);

    expect($product->variant_attribute_ids)->toBe([$this->set->id])
        ->and($product->has_color_variant)->toBeFalse()
        ->and($product->has_size_variant)->toBeFalse()
        ->and((float) $product->price)->toBe(300.0)
        ->and($product->variants()->orderBy('id')->get()->map(fn ($v) => [$v->attribute_value_ids, $v->attributes])->all())
        ->toBe([[[$this->six->id], ['Set' => '6pcs']], [[$this->twelve->id], ['Set' => '12pcs']]])
        ->and($product->attributeValues()->pluck('attribute_values.id')->sort()->values()->all())
        ->toBe([$this->six->id, $this->twelve->id]);
});

test('values are stored in the product\'s attribute order whatever order they arrive in', function () {
    $product = saveCustomVariantProduct([$this->color->id, $this->set->id], [
        customVariantRow([$this->six->id, $this->red->id], ['is_default' => true]),
    ]);

    $variant = $product->variants()->firstOrFail();
    expect($variant->attribute_value_ids)->toBe([$this->red->id, $this->six->id])
        ->and($variant->attributes)->toBe(['Color' => 'Red', 'Set' => '6pcs'])
        ->and($variant->color_value_id)->toBeNull()
        ->and($product->has_color_variant)->toBeTrue();
});

test('two variants with the same colour and size but different sets can coexist', function () {
    $size = Attribute::firstWhere('name', 'Size');
    $medium = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);

    $product = saveCustomVariantProduct([$this->color->id, $size->id, $this->set->id], [
        customVariantRow([$this->blue->id, $medium->id, $this->six->id], ['is_default' => true]),
        customVariantRow([$this->blue->id, $medium->id, $this->twelve->id]),
    ]);

    // The older colour/size columns (unique per product) are only written
    // for products built from Color and Size alone.
    expect($product->variants()->get()->map(fn ($v) => [$v->attributes, $v->color_value_id, $v->size_value_id])->all())->toBe([
        [['Color' => 'Blue', 'Size' => 'M', 'Set' => '6pcs'], null, null],
        [['Color' => 'Blue', 'Size' => 'M', 'Set' => '12pcs'], null, null],
    ]);
});

test('a variant missing a value for one of the attributes is rejected', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.products.store'), customVariantPayload([$this->color->id, $this->set->id], [
            customVariantRow([$this->red->id], ['is_default' => true]),
        ]))
        ->assertSessionHasErrors(['variants.0' => 'Each variant needs exactly one value for every variant attribute.']);
});

test('a value of an attribute the product does not use is rejected', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.products.store'), customVariantPayload([$this->set->id], [
            customVariantRow([$this->red->id], ['is_default' => true]),
        ]))
        ->assertSessionHasErrors('variants.0');

    expect(Product::where('slug', 'steel-spoon')->exists())->toBeFalse();
});

test('the same combination twice is rejected', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.products.store'), customVariantPayload([$this->set->id], [
            customVariantRow([$this->six->id], ['is_default' => true]),
            customVariantRow([$this->six->id]),
        ]))
        ->assertSessionHasErrors(['variants.1' => 'Duplicate combination — each variant must be a different combination of values.']);
});

test('editing keeps a variant\'s id, drops an unused one and freezes one a cart still holds', function () {
    $product = saveCustomVariantProduct([$this->set->id], [
        customVariantRow([$this->six->id], ['is_default' => true]),
        customVariantRow([$this->twelve->id]),
    ]);
    $six = $product->variants()->get()->first(fn ($v) => $v->attribute_value_ids === [$this->six->id]);
    $twelve = $product->variants()->get()->first(fn ($v) => $v->attribute_value_ids === [$this->twelve->id]);
    Cart::create(['user_id' => User::factory()->create()->id])->items()->create([
        'product_id' => $product->id, 'variant_id' => $twelve->id, 'quantity' => 1, 'price' => 500,
    ]);

    actingAs($this->admin, 'admin')
        ->put(route('admin.products.update', $product), customVariantPayload([$this->set->id], [
            customVariantRow([$this->six->id], ['is_default' => true, 'price' => 320]),
        ], ['stock_token' => ProductStockToken::for($product)]))
        ->assertSessionHasNoErrors();

    expect((float) $six->fresh()->price)->toBe(320.0)
        ->and($twelve->fresh())->not->toBeNull()
        ->and($product->attributeValues()->pluck('attribute_values.id')->all())->toBe([$this->six->id]);
});

test('an image chosen for a value of an attribute that allows images serves every variant with it', function () {
    $this->set->update(['has_images' => true]);

    $product = saveCustomVariantProduct([$this->color->id, $this->set->id], [
        customVariantRow([$this->red->id, $this->six->id], ['is_default' => true]),
        customVariantRow([$this->red->id, $this->twelve->id]),
        customVariantRow([$this->blue->id, $this->twelve->id]),
    ], [
        'images' => ['/storage/products/twelve.jpg', '/storage/products/red.jpg'],
        'media_tags' => ["value:{$this->twelve->id}", "value:{$this->red->id}"],
    ]);

    $images = $product->variants()->with('media')->get()->mapWithKeys(fn ($v) => [implode('/', $v->attributes) => $v->image]);

    // Red is the first attribute, so Red/12pcs shows the Red photo; Blue has
    // no colour photo, so Blue/12pcs falls back to the 12pcs one.
    expect($images->all())->toBe([
        'Red/6pcs' => '/storage/products/red.jpg',
        'Red/12pcs' => '/storage/products/red.jpg',
        'Blue/12pcs' => '/storage/products/twelve.jpg',
    ]);
});

test('the product page lists the attributes in the product\'s order with each variant\'s values', function () {
    $product = saveCustomVariantProduct([$this->set->id, $this->color->id], [
        customVariantRow([$this->red->id, $this->six->id], ['is_default' => true]),
    ]);

    auth('admin')->logout();

    $this->get(route('product.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.attributes.0.name', 'Set')
            ->where('product.attributes.1.name', 'Color')
            ->where('product.variants.0.attributes', ['Set' => '6pcs', 'Color' => 'Red'])
            ->where('product.variants.0.attribute_value_ids', [$this->six->id, $this->red->id]));
});

test('an order keeps the options bought even after the value is renamed', function () {
    // Created directly: an admin login in the same test would send the
    // shopper to the admin dashboard (RedirectAdminToDashboard).
    $product = Product::factory()->create(['price' => 550, 'stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay(), 'variant_attribute_ids' => [$this->set->id]]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 550, 'stock_quantity' => 10, 'is_default' => true,
        'attribute_value_ids' => [$this->twelve->id], 'attributes' => ['Set' => '12pcs'],
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->post(route('checkout.store'), [
        'name' => 'Test Buyer', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'price' => 550]],
    ])->assertSessionHasNoErrors();

    $item = Order::where('user_id', $user->id)->firstOrFail()->items()->firstOrFail();
    expect($item->product_snapshot['variant_attributes'])->toBe(['Set' => '12pcs']);

    $this->twelve->update(['value' => '12 pieces']);

    expect($variant->fresh()->attributes)->toBe(['Set' => '12 pieces'])
        ->and($item->fresh()->variantLabel())->toBe('Set: 12pcs');
});

test('an order line without a snapshot shows the live variant\'s options', function () {
    $variant = ProductVariant::factory()->create(['attributes' => ['Set' => '6pcs']]);
    $address = ['name' => 'Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];
    $order = Order::create([
        'order_number' => 'ORD-SNAP-1', 'user_id' => User::factory()->create()->id, 'status' => 'pending', 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 300, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 300,
        'shipping_address' => $address, 'billing_address' => $address,
    ]);
    $item = $order->items()->create([
        'product_id' => $variant->product_id, 'variant_id' => $variant->id, 'product_name' => 'Spoon',
        'price' => 300, 'quantity' => 1, 'subtotal' => 300, 'product_snapshot' => [],
    ]);

    expect($item->variantLabel())->toBe('Set: 6pcs');
});
