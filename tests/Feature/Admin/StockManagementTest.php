<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\InventoryType;
use App\Mail\LowStockAlertMail;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Services\StockAdjustmentService;
use App\Support\MotherPasswordGate;
use App\Support\ProductStockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Owner decisions 2026-11-14: Admin › Stock Management, and the same rules for
 * stock changed on the product edit form.
 */

beforeEach(function () {
    $this->admin = createAdmin(['name' => 'Stock Keeper', 'password' => bcrypt('password')]);
});

function adjustPayload(Product $product, ?ProductVariant $variant, string $direction, int $quantity, array $overrides = []): array
{
    return array_merge([
        'product_id' => $product->id,
        'variant_id' => $variant?->id,
        'direction' => $direction,
        'quantity' => $quantity,
        'reason' => 'Shipment check',
        'password' => 'password',
    ], $overrides);
}

test('the page lists a product without variants as one row and each variant as its own row', function () {
    $simple = Product::factory()->create(['name' => 'Alpha Simple', 'stock_quantity' => 8]);
    $withVariants = Product::factory()->create(['name' => 'Beta Variants', 'stock_quantity' => 0]);
    ProductVariant::factory()->create(['product_id' => $withVariants->id, 'stock_quantity' => 3, 'attributes' => ['Color' => 'Red']]);
    ProductVariant::factory()->create(['product_id' => $withVariants->id, 'stock_quantity' => 4, 'attributes' => ['Color' => 'Blue']]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.stock.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/stock/index')
            ->where('products.data.0.name', 'Alpha Simple')
            ->where('products.data.0.stock_quantity', 8)
            ->where('products.data.0.variants', [])
            ->where('products.data.1.name', 'Beta Variants')
            ->where('products.data.1.variants.0.label', 'Red')
            ->where('products.data.1.variants.0.stock_quantity', 3)
            ->where('products.data.1.variants.1.label', 'Blue')
            ->where('canAdjust', true));
});

test('search matches name, product or variant SKU, and an exact price', function (string $search) {
    $match = Product::factory()->create(['name' => 'Copper Kettle', 'sku' => 'KET-100', 'price' => 1450]);
    ProductVariant::factory()->create(['product_id' => $match->id, 'sku' => 'KET-100-RED', 'price' => 1499]);
    Product::factory()->create(['name' => 'Garden Hose', 'sku' => 'HOSE-9', 'price' => 300]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.stock.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Copper Kettle'));
})->with(['name' => 'kettle', 'product sku' => 'KET-100', 'variant sku' => 'RED', 'product price' => '1450', 'variant price' => '1499']);

test('category and sub-category filter on their own', function () {
    $kitchen = Category::factory()->create(['name' => 'Kitchen']);
    $cookware = SubCategory::factory()->create(['category_id' => $kitchen->id, 'name' => 'Cookware']);
    $cutlery = SubCategory::factory()->create(['category_id' => $kitchen->id, 'name' => 'Cutlery']);
    Product::factory()->create(['name' => 'Pan', 'category_id' => $kitchen->id, 'sub_category_id' => $cookware->id]);
    Product::factory()->create(['name' => 'Knife', 'category_id' => $kitchen->id, 'sub_category_id' => $cutlery->id]);
    Product::factory()->create(['name' => 'Lamp']);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.stock.index', ['category_id' => $kitchen->id]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));

    $this->get(route('admin.stock.index', ['sub_category_id' => $cutlery->id]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Knife'));
});

test('adding stock with the right password raises it and records it in the stock log and Audit Trail', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 5))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($product->refresh()->stock_quantity)->toBe(15);

    $log = InventoryLog::query()->latest('id')->first();
    expect($log->type)->toBe(InventoryType::ADJUSTMENT)
        ->and($log->quantity)->toBe(5)
        ->and($log->note)->toContain('Stock Keeper')->toContain('10 → 15')->toContain('Shipment check');

    $activity = Activity::query()->where('log_name', 'stock')->sole();
    expect($activity->event)->toBe('stock_adjusted')
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties['before'])->toBe(10)
        ->and($activity->properties['after'])->toBe(15)
        ->and($activity->properties['change'])->toBe(5)
        ->and($activity->properties['source'])->toBe(StockAdjustmentService::SOURCE_STOCK_PAGE);
});

test('removing a variant\'s stock lowers it and keeps the product total equal to its variants', function () {
    $product = Product::factory()->create(['stock_quantity' => 999]);
    $red = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 6]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 4]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.stock.adjust'), adjustPayload($product, $red, 'remove', 2))->assertSessionHasNoErrors();

    expect($red->refresh()->stock_quantity)->toBe(4)
        ->and($product->refresh()->stock_quantity)->toBe(8)
        ->and(InventoryLog::query()->latest('id')->first()->quantity)->toBe(-2);
});

test('stock can never go below zero', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'remove', 4))->assertSessionHasErrors('quantity');

    expect($product->refresh()->stock_quantity)->toBe(3)
        ->and(InventoryLog::query()->count())->toBe(0);
});

test('a wrong password changes nothing, and five wrong attempts lock it', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);

    actingAs($this->admin, 'admin');

    for ($attempt = 0; $attempt < MotherPasswordGate::MAX_FAILURES; $attempt++) {
        $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 1, ['password' => 'wrong']))
            ->assertSessionHasErrors(['password' => 'Incorrect password.']);
    }

    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 1))->assertSessionHasErrors('password');

    expect($product->refresh()->stock_quantity)->toBe(3)
        ->and(Activity::query()->where('log_name', 'stock')->count())->toBe(0);
});

test('a variant product must be adjusted per variant, and a variant must belong to its product', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 5]);
    $foreign = ProductVariant::factory()->create(['stock_quantity' => 5]);

    actingAs($this->admin, 'admin');

    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 1))->assertSessionHasErrors('quantity');
    $this->post(route('admin.stock.adjust'), adjustPayload($product, $foreign, 'add', 1))->assertNotFound();

    expect($foreign->refresh()->stock_quantity)->toBe(5);
});

test('the page and the adjustment need the Stock Management permission', function () {
    $viewOnly = createAdmin(['permissions' => ['stock' => ['view']]]);
    $none = createAdmin(['permissions' => ['products' => ['view']]]);
    $product = Product::factory()->create(['stock_quantity' => 3]);

    actingAs($none, 'admin');
    $this->get(route('admin.stock.index'))->assertForbidden();

    actingAs($viewOnly, 'admin');
    $this->get(route('admin.stock.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canAdjust', false));
    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 1))->assertForbidden();

    expect($product->refresh()->stock_quantity)->toBe(3);
});

test('a manual removal crossing the low-stock threshold alerts the Stock Alert Email', function () {
    Mail::fake();
    Setting::updateOrCreate(['key' => 'stock_alert_email'], ['value' => 'stock@shop.test', 'group' => 'general']);
    $product = Product::factory()->create(['stock_quantity' => 7, 'low_stock_threshold' => 5]);

    actingAs($this->admin, 'admin');
    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'remove', 3))->assertSessionHasNoErrors();

    Mail::assertQueued(LowStockAlertMail::class, fn (LowStockAlertMail $mail) => $mail->hasTo('stock@shop.test'));
});

test('with no Stock Alert Email set, low-stock alerts still go to the main admin', function () {
    Mail::fake();
    createMotherAdmin(['email' => 'owner@shop.test', 'password' => bcrypt('x')]);
    $product = Product::factory()->create(['stock_quantity' => 7, 'low_stock_threshold' => 5]);

    actingAs($this->admin, 'admin');
    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'remove', 3))->assertSessionHasNoErrors();

    Mail::assertQueued(LowStockAlertMail::class, fn (LowStockAlertMail $mail) => $mail->hasTo('owner@shop.test'));
});

test('the Stock Alert Email can be saved in Settings', function () {
    actingAs(createMotherAdmin(['password' => bcrypt('password')]), 'admin');

    $this->put(route('admin.settings.update'), ['stock_alert_email' => 'stock@shop.test'])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'stock_alert_email')->value('value'))->toBe('stock@shop.test');
});

// ---- The product edit form follows the same rules ----

function editPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'stock_token' => ProductStockToken::for($product),
        'name' => $product->name,
        'slug' => $product->slug,
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => $product->category_id,
        'sub_category_id' => $product->sub_category_id,
        'price' => 500,
        'stock_quantity' => $product->stock_quantity,
        'is_active' => true,
    ], $overrides);
}

test('changing stock on the edit form without the password is refused and changes nothing', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), editPayload($product, ['stock_quantity' => 25, 'name' => 'Renamed']))
        ->assertSessionHasErrors(['stock_password' => 'Enter your password to change stock.']);

    expect($product->refresh()->stock_quantity)->toBe(10)
        ->and($product->name)->not->toBe('Renamed');
});

test('a wrong password on the edit form is refused', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), editPayload($product, ['stock_quantity' => 25, 'stock_password' => 'wrong']))
        ->assertSessionHasErrors(['stock_password' => 'Incorrect password.']);

    expect($product->refresh()->stock_quantity)->toBe(10);
});

test('saving the edit form without a stock change needs no password', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), editPayload($product, ['name' => 'Renamed Only']))->assertSessionHasNoErrors();

    expect($product->refresh()->name)->toBe('Renamed Only')
        ->and(Activity::query()->where('log_name', 'stock')->count())->toBe(0);
});

test('a stock change on the edit form with the password is recorded like Stock Management', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), editPayload($product, [
        'stock_quantity' => 4,
        'stock_password' => 'password',
        'stock_reason' => 'Recount',
    ]))->assertSessionHasNoErrors();

    expect($product->refresh()->stock_quantity)->toBe(4);

    $activity = Activity::query()->where('log_name', 'stock')->sole();
    expect($activity->properties['before'])->toBe(10)
        ->and($activity->properties['after'])->toBe(4)
        ->and($activity->properties['reason'])->toBe('Recount')
        ->and($activity->properties['source'])->toBe(StockAdjustmentService::SOURCE_PRODUCT_EDIT)
        ->and(InventoryLog::query()->latest('id')->first()->quantity)->toBe(-6);
});

test('the edit form\'s live stock endpoint returns the current stock, variant keys and a fresh token', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 9, 'attribute_value_ids' => [7, 3]]);

    actingAs($this->admin, 'admin');

    $this->getJson(route('admin.products.stock-state', $product))
        ->assertOk()
        ->assertJsonPath('variants.0.key', '3-7')
        ->assertJsonPath('variants.0.stock_quantity', 9)
        ->assertJsonPath('token', ProductStockToken::for($product));

    $variant->update(['stock_quantity' => 2]);

    $this->getJson(route('admin.products.stock-state', $product))->assertJsonPath('variants.0.stock_quantity', 2);
});

// ---- Edit form: new variants, removed variants and switching variant mode ----

function stockEditColors(): array
{
    $color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);

    return [
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red']),
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue']),
    ];
}

function stockEditVariantPayload(Product $product, array $variants, array $overrides = []): array
{
    return editPayload($product, array_merge(['has_color_variant' => true, 'variants' => $variants], $overrides));
}

test('a new variant\'s starting stock needs the password and is recorded as 0 → n', function () {
    [$red, $blue] = stockEditColors();
    $product = Product::factory()->create(['has_color_variant' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => $red->id, 'stock_quantity' => 6, 'price' => 100, 'sku' => 'NEW-RED', 'is_default' => true]);
    $variants = [
        ['color_value_id' => $red->id, 'price' => 100, 'stock_quantity' => 6, 'is_default' => true, 'sku' => 'NEW-RED'],
        ['color_value_id' => $blue->id, 'price' => 100, 'stock_quantity' => 5, 'is_default' => false, 'sku' => 'NEW-BLUE'],
    ];

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $variants))
        ->assertSessionHasErrors('stock_password');
    expect(ProductVariant::where('product_id', $product->id)->count())->toBe(1);

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $variants, ['stock_password' => 'password']))
        ->assertSessionHasNoErrors();

    $activity = Activity::query()->where('log_name', 'stock')->sole();
    expect($activity->properties['before'])->toBe(0)
        ->and($activity->properties['after'])->toBe(5)
        ->and($activity->properties['item'])->toContain('Blue');
});

test('removing a variant that had stock needs the password and is recorded as n → 0', function () {
    [$red, $blue] = stockEditColors();
    $product = Product::factory()->create(['has_color_variant' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => $red->id, 'stock_quantity' => 6, 'price' => 100, 'sku' => 'DEL-RED', 'is_default' => true, 'attributes' => ['Color' => 'Red']]);
    $blueVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => $blue->id, 'stock_quantity' => 4, 'price' => 100, 'sku' => 'DEL-BLUE', 'is_default' => false, 'attributes' => ['Color' => 'Blue']]);
    $onlyRed = [['color_value_id' => $red->id, 'price' => 100, 'stock_quantity' => 6, 'is_default' => true, 'sku' => 'DEL-RED']];

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $onlyRed))
        ->assertSessionHasErrors('stock_password');
    expect(ProductVariant::whereKey($blueVariant->id)->exists())->toBeTrue();

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $onlyRed, ['stock_password' => 'password']))
        ->assertSessionHasNoErrors();

    expect(ProductVariant::whereKey($blueVariant->id)->exists())->toBeFalse();

    $activity = Activity::query()->where('log_name', 'stock')->sole();
    expect((int) $activity->subject_id)->toBe($blueVariant->id)
        ->and($activity->properties['before'])->toBe(4)
        ->and($activity->properties['after'])->toBe(0)
        ->and($activity->properties['item'])->toContain('variant removed');

    $log = InventoryLog::query()->latest('id')->first();
    expect($log->variant_id)->toBeNull()
        ->and($log->quantity)->toBe(-4)
        ->and($log->note)->toContain('Blue');
});

test('switching a product from no variants to variants needs the password and records both sides', function () {
    [$red] = stockEditColors();
    $product = Product::factory()->create(['stock_quantity' => 10]);
    $variants = [['color_value_id' => $red->id, 'price' => 100, 'stock_quantity' => 7, 'is_default' => true, 'sku' => 'SWITCH-RED']];

    actingAs($this->admin, 'admin');

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $variants))
        ->assertSessionHasErrors('stock_password');

    $this->put(route('admin.products.update', $product), stockEditVariantPayload($product, $variants, ['stock_password' => 'password']))
        ->assertSessionHasNoErrors();

    $changes = Activity::query()->where('log_name', 'stock')->orderBy('id')->get()
        ->map(fn (Activity $a): array => [$a->properties['variant_id'] === null ? 'product' : 'variant', $a->properties['before'], $a->properties['after']])
        ->all();

    expect($changes)->toEqualCanonicalizing([['product', 10, 0], ['variant', 0, 7]]);
});

test('switching a product from variants to no variants needs the password and records both sides', function () {
    [$red] = stockEditColors();
    $product = Product::factory()->create(['has_color_variant' => true, 'stock_quantity' => 6]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'color_value_id' => $red->id, 'stock_quantity' => 6, 'price' => 100, 'sku' => 'BACK-RED', 'is_default' => true]);

    actingAs($this->admin, 'admin');

    $flat = editPayload($product, ['has_color_variant' => false, 'variants' => [], 'stock_quantity' => 9]);

    $this->put(route('admin.products.update', $product), $flat)->assertSessionHasErrors('stock_password');

    $this->put(route('admin.products.update', $product), $flat + ['stock_password' => 'password'])->assertSessionHasNoErrors();

    $changes = Activity::query()->where('log_name', 'stock')->orderBy('id')->get()
        ->map(fn (Activity $a): array => [$a->properties['variant_id'] === null ? 'product' : 'variant', $a->properties['before'], $a->properties['after']])
        ->all();

    expect($product->refresh()->stock_quantity)->toBe(9)
        ->and($changes)->toEqualCanonicalizing([['product', 0, 9], ['variant', 6, 0]]);
});

test('the Audit Trail can be filtered to stock activity', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);
    $mother = createMotherAdmin(['password' => bcrypt('password')]);

    actingAs($this->admin, 'admin');
    $this->post(route('admin.stock.adjust'), adjustPayload($product, null, 'add', 2))->assertSessionHasNoErrors();
    activity('admin')->causedBy($this->admin)->log('Unrelated admin activity');

    actingAs($mother, 'admin');
    $this->get(route('admin.audit-trail.index', ['log_name' => 'stock']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logNameOptions.stock', 'Stock Activity')
            ->has('activities.data', 1)
            ->where('activities.data.0.log_name', 'stock'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createStockPayload(array $overrides = []): array
{
    $subCategory = SubCategory::factory()->create();

    return array_merge([
        'name' => 'Fresh Product',
        'slug' => 'fresh-product',
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'images' => [UploadedFile::fake()->image('product.jpg', 400, 400)],
        'category_id' => $subCategory->category_id,
        'sub_category_id' => $subCategory->id,
        'price' => 500,
        'stock_quantity' => 50,
        'is_active' => true,
    ], $overrides);
}

test('creating a product with starting stock needs the password, and nothing is created without it', function (array $password, string $error) {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.products.store'), createStockPayload($password))
        ->assertSessionHasErrors(['stock_password' => $error]);

    expect(Product::query()->count())->toBe(0)
        ->and(InventoryLog::query()->count())->toBe(0)
        ->and(Activity::query()->where('log_name', 'stock')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'no password' => [[], 'Enter your password to change stock.'],
    'wrong password' => [['stock_password' => 'wrong'], 'Incorrect password.'],
]);

test('a new product\'s starting stock is recorded as 0 → n from the new product form', function () {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.products.store'), createStockPayload(['stock_password' => 'password', 'stock_reason' => 'First delivery']))
        ->assertSessionHasNoErrors();

    $product = Product::query()->sole();
    $activity = Activity::query()->where('log_name', 'stock')->sole();
    $log = InventoryLog::query()->sole();

    expect($product->stock_quantity)->toBe(50)
        ->and($activity->properties['before'])->toBe(0)
        ->and($activity->properties['after'])->toBe(50)
        ->and($activity->properties['reason'])->toBe('First delivery')
        ->and($activity->properties['source'])->toBe(StockAdjustmentService::SOURCE_PRODUCT_CREATE)
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($log->type)->toBe(InventoryType::ADJUSTMENT)
        ->and($log->quantity)->toBe(50)
        ->and($log->note)->toContain('via new product form');
});

test('a new product with variants records each variant\'s starting stock', function () {
    Storage::fake('public');
    [$red, $blue] = stockEditColors();
    actingAs($this->admin, 'admin');

    $this->post(route('admin.products.store'), createStockPayload([
        'stock_password' => 'password',
        'price' => null,
        'stock_quantity' => null,
        'has_color_variant' => true,
        'variants' => [
            ['color_value_id' => $red->id, 'price' => 100, 'stock_quantity' => 20, 'is_default' => true, 'sku' => 'CREATE-RED'],
            ['color_value_id' => $blue->id, 'price' => 100, 'stock_quantity' => 30, 'is_default' => false, 'sku' => 'CREATE-BLUE'],
        ],
    ]))->assertSessionHasNoErrors();

    $activities = Activity::query()->where('log_name', 'stock')->get();

    expect($activities->map(fn (Activity $a): array => [$a->properties['before'], $a->properties['after']])->sortBy(1)->values()->all())
        ->toBe([[0, 20], [0, 30]])
        ->and($activities->pluck('properties.variant_id')->filter()->count())->toBe(2)
        ->and((int) InventoryLog::query()->sum('quantity'))->toBe(50)
        ->and(Product::query()->sole()->stock_quantity)->toBe(50);
});

test('a new product with no stock needs no password and records no stock change', function () {
    Storage::fake('public');
    actingAs($this->admin, 'admin');

    $this->post(route('admin.products.store'), createStockPayload(['stock_quantity' => 0]))
        ->assertSessionHasNoErrors();

    expect(Product::query()->count())->toBe(1)
        ->and(InventoryLog::query()->count())->toBe(0)
        ->and(Activity::query()->where('log_name', 'stock')->count())->toBe(0);
});
