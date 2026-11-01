<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Models\User;
use App\Support\ProductStockToken;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(createAdmin(['name' => 'Unique Admin', 'email' => 'unique@example.com', 'password' => bcrypt('password')]), 'admin');

    $this->category = Category::factory()->create();
    $this->subCategory = SubCategory::factory()->create(['category_id' => $this->category->id]);
    $this->color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $this->size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $this->red = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    $this->blue = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Blue', 'color_hex' => '#0000ff']);
    $this->green = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Green', 'color_hex' => '#00ff00']);
});

function uniqueTestMigration(): object
{
    return require glob(database_path('migrations/*_add_unique_indexes_to_variant_skus_and_attribute_values.php'))[0];
}

/**
 * MED-06 deferred: this integration-preparation branch intentionally
 * excludes database/migrations/2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values.php
 * until its production preflight is complete (see the remediation tracker).
 * Tests below that depend on the real unique indexes, or on the migration
 * file itself, are skipped on this branch only — the checkpoint branch
 * (backup/shoppydrive-remediation-checkpoint-2026-11-01) keeps this file and
 * all its tests complete and unmodified.
 */
if (! function_exists('med06MigrationExcludedFromIntegrationBranch')) {
    function med06MigrationExcludedFromIntegrationBranch(): bool
    {
        return glob(database_path('migrations/*_add_unique_indexes_to_variant_skus_and_attribute_values.php')) === [];
    }
}

if (! defined('MED06_DEFERRED_SKIP_REASON')) {
    define('MED06_DEFERRED_SKIP_REASON', 'MED-06 deferred: unique indexes/migration intentionally excluded from this integration branch.');
}

/**
 * @param  array<int, array{0: AttributeValue, 1: string|null}>  $rows  Colour and SKU, the first being the default.
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantFormPayload(array $rows, array $overrides = []): array
{
    return array_merge([
        'name' => 'Unique Shirt',
        'slug' => 'unique-shirt',
        'category_id' => test()->category->id,
        'sub_category_id' => test()->subCategory->id,
        'has_color_variant' => true,
        'is_active' => true,
        'variants' => array_map(fn (array $row, int $index) => [
            'color_value_id' => $row[0]->id, 'price' => 500, 'stock_quantity' => 5, 'is_default' => $index === 0, 'sku' => $row[1],
        ], $rows, array_keys($rows)),
    ], $overrides);
}

function productWithSkus(array $rows): Product
{
    test()->post(route('admin.products.store'), variantFormPayload($rows))->assertSessionHasNoErrors();

    return Product::where('slug', 'unique-shirt')->firstOrFail();
}

function saveProduct(Product $product, array $rows): TestResponse
{
    return test()->put(route('admin.products.update', $product), ['stock_token' => ProductStockToken::for($product)] + variantFormPayload($rows));
}

test('the database refuses a duplicate SKU', function () {
    ProductVariant::factory()->create(['sku' => 'SAME-1']);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'SAME-1']))->toThrow(QueryException::class);
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('many variants can have no SKU', function () {
    $product = productWithSkus([[$this->red, null], [$this->blue, '   ']]);

    expect($product->variants()->whereNull('sku')->count())->toBe(2);
});

test('the database refuses a duplicate value within an attribute, not across attributes', function () {
    expect(fn () => AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Red', 'color_hex' => '#111111']))->toThrow(QueryException::class);

    expect(AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'Red'])->exists)->toBeTrue();
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('existing validation still gives the friendly duplicate errors', function () {
    ProductVariant::factory()->create(['sku' => 'TAKEN-1']);

    $this->post(route('admin.products.store'), variantFormPayload([[$this->red, 'TAKEN-1']]))->assertSessionHasErrors('variants.0.sku');
    $this->post(route('admin.products.store'), variantFormPayload([[$this->red, 'SAME'], [$this->blue, 'same']]))->assertSessionHasErrors('variants.1.sku');
    $this->post(route('admin.attribute-values.store'), ['attribute_id' => $this->color->id, 'value' => 'Red', 'color_hex' => '#222222'])->assertSessionHasErrors('value');
});

test('two variants of a product can swap SKUs', function () {
    $product = productWithSkus([[$this->red, 'SKU-A'], [$this->blue, 'SKU-B']]);

    saveProduct($product, [[$this->red, 'SKU-B'], [$this->blue, 'SKU-A']])->assertSessionHasNoErrors();

    expect($product->variants()->where('color_value_id', $this->red->id)->value('sku'))->toBe('SKU-B');
    expect($product->variants()->where('color_value_id', $this->blue->id)->value('sku'))->toBe('SKU-A');
});

test('a new variant can take over the SKU of one the save removes', function () {
    $product = productWithSkus([[$this->red, 'SKU-A']]);

    saveProduct($product, [[$this->blue, 'SKU-A']])->assertSessionHasNoErrors();

    expect($product->variants()->pluck('color_value_id')->all())->toBe([$this->blue->id]);
    expect($product->variants()->value('sku'))->toBe('SKU-A');
});

test('resaving unchanged SKUs works', function () {
    $product = productWithSkus([[$this->red, 'SKU-A'], [$this->blue, 'SKU-B']]);

    saveProduct($product, [[$this->red, 'SKU-A'], [$this->blue, 'SKU-B']])->assertSessionHasNoErrors();

    expect($product->variants()->orderBy('id')->pluck('sku')->all())->toBe(['SKU-A', 'SKU-B']);
});

test('a retired variant keeps its SKU, and reusing it gives a friendly error with nothing saved', function () {
    $product = productWithSkus([[$this->red, 'SKU-A'], [$this->blue, 'SKU-B']]);
    $red = $product->variants()->where('color_value_id', $this->red->id)->firstOrFail();
    $cart = Cart::create(['user_id' => User::factory()->create()->id]);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => $red->id, 'quantity' => 1, 'price' => 500]);

    saveProduct($product, [[$this->blue, 'SKU-B']])->assertSessionHasNoErrors();

    $before = $product->refresh()->variants()->orderBy('id')->pluck('sku', 'id')->all();

    saveProduct($product, [[$this->blue, 'SKU-B'], [$this->green, 'SKU-A']])->assertSessionHasErrors('variants');

    expect($red->refresh()->sku)->toBe('SKU-A');
    expect($product->variants()->orderBy('id')->pluck('sku', 'id')->all())->toBe($before);
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('a save that loses a race for a SKU gets a friendly error and leaves nothing behind', function () {
    ProductVariant::creating(function (ProductVariant $variant): void {
        if ($variant->sku === 'RACED-1' && DB::table('product_variants')->where('sku', 'RACED-1')->doesntExist()) {
            $other = Product::factory()->create();
            DB::table('product_variants')->insert(['product_id' => $other->id, 'sku' => 'RACED-1', 'attributes' => '[]', 'stock_quantity' => 0]);
        }
    });

    $this->post(route('admin.products.store'), variantFormPayload([[$this->red, 'RACED-1']]))->assertSessionHasErrors('variants');

    expect(Product::where('slug', 'unique-shirt')->exists())->toBeFalse();
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('other unique failures are not reported as a SKU problem', function () {
    ProductVariant::creating(fn () => throw new UniqueConstraintViolationException('sqlite', 'insert', [], new RuntimeException('UNIQUE constraint failed: products.slug')));

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('admin.products.store'), variantFormPayload([[$this->red, 'OTHER-1']])))->toThrow(UniqueConstraintViolationException::class);
});

test('an attribute value that loses a race gets a friendly error', function () {
    AttributeValue::creating(function (AttributeValue $value): void {
        if ($value->value === 'Raced') {
            DB::table('attribute_values')->insertOrIgnore(['attribute_id' => $value->attribute_id, 'value' => 'Raced', 'sort_order' => 0]);
        }
    });

    $this->post(route('admin.attribute-values.store'), ['attribute_id' => $this->size->id, 'value' => 'Raced'])->assertSessionHasErrors('value');

    expect(AttributeValue::where('value', 'Raced')->count())->toBe(1);
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('the migration rolls back and reapplies cleanly', function () {
    $migration = uniqueTestMigration();

    $migration->down();

    expect(Schema::hasIndex('product_variants', 'product_variants_sku_unique'))->toBeFalse();
    expect(Schema::hasIndex('attribute_values', 'attribute_values_attribute_id_value_unique'))->toBeFalse();

    $migration->up();

    expect(Schema::hasIndex('product_variants', 'product_variants_sku_unique'))->toBeTrue();
    expect(Schema::hasIndex('attribute_values', 'attribute_values_attribute_id_value_unique'))->toBeTrue();
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('the migration refuses duplicates, names them, and changes nothing', function () {
    $migration = uniqueTestMigration();
    $migration->down();

    $first = ProductVariant::factory()->create(['sku' => 'DUP-1']);
    $second = ProductVariant::factory()->create(['sku' => 'DUP-1']);
    $value = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'M']);
    AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'M']);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, "[{$first->id}]");

    expect(Schema::hasIndex('product_variants', 'product_variants_sku_unique'))->toBeFalse();
    expect(Schema::hasIndex('attribute_values', 'attribute_values_attribute_id_value_unique'))->toBeFalse();
    expect(ProductVariant::whereKey([$first->id, $second->id])->pluck('sku')->all())->toBe(['DUP-1', 'DUP-1']);
    expect(AttributeValue::where('value', 'M')->count())->toBe(2);
    expect($value->exists)->toBeTrue();

    ProductVariant::whereKey($second->id)->delete();
    AttributeValue::where('value', 'M')->where('id', '!=', $value->id)->delete();
    $migration->up();
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('the migration resumes when only the first index exists', function () {
    $migration = uniqueTestMigration();
    $migration->down();

    Schema::table('product_variants', fn (Blueprint $table) => $table->unique('sku', 'product_variants_sku_unique'));

    $migration->up();

    expect(Schema::hasIndex('attribute_values', 'attribute_values_attribute_id_value_unique'))->toBeTrue();
})->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);
