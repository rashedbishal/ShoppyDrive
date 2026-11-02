<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Support\VariantAttributeDuplicateCheck;
use Illuminate\Support\Facades\DB;

/**
 * This branch's MED-06 migration already adds both unique indexes by
 * default (no duplicates exist until a test creates some), so any test
 * that needs to insert a real duplicate must drop them first — the same
 * pattern VariantAndAttributeUniquenessTest.php uses via its own
 * uniqueTestMigration() helper.
 */
function dropVariantAttributeUniqueIndexes(): void
{
    (require glob(database_path('migrations/*_add_unique_indexes_to_variant_skus_and_attribute_values.php'))[0])->down();
}

test('find() returns no duplicates on clean data', function () {
    ProductVariant::factory()->create(['sku' => 'UNIQUE-1']);
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);

    $duplicates = VariantAttributeDuplicateCheck::find();

    expect(VariantAttributeDuplicateCheck::hasDuplicates($duplicates))->toBeFalse();
    expect($duplicates['sku'])->toBe([]);
    expect($duplicates['attribute_value'])->toBe([]);
});

test('find() detects duplicate SKUs and names the affected rows', function () {
    dropVariantAttributeUniqueIndexes();
    $first = ProductVariant::factory()->create(['sku' => 'DUP-1']);
    $second = ProductVariant::factory()->create(['sku' => 'DUP-1']);
    ProductVariant::factory()->create(['sku' => 'UNIQUE-2']);

    $duplicates = VariantAttributeDuplicateCheck::find();

    expect($duplicates['sku'])->toBe([
        ['value' => 'DUP-1', 'rows' => 2, 'ids' => [$first->id, $second->id]],
    ]);
    expect($duplicates['attribute_value'])->toBe([]);
});

test('NULL SKUs are never treated as duplicates, no matter how many', function () {
    ProductVariant::factory()->count(3)->create(['sku' => null]);

    expect(VariantAttributeDuplicateCheck::find()['sku'])->toBe([]);
});

test('empty-string SKUs are a real, comparable value, not treated like NULL', function () {
    dropVariantAttributeUniqueIndexes();
    $first = ProductVariant::factory()->create(['sku' => '']);
    $second = ProductVariant::factory()->create(['sku' => '']);

    expect(VariantAttributeDuplicateCheck::find()['sku'])->toBe([
        ['value' => '', 'rows' => 2, 'ids' => [$first->id, $second->id]],
    ]);
});

test('find() detects duplicate attribute values scoped to the same attribute, not across attributes', function () {
    dropVariantAttributeUniqueIndexes();
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $color = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $first = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);
    $second = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);
    AttributeValue::create(['attribute_id' => $color->id, 'value' => 'M']);

    expect(VariantAttributeDuplicateCheck::find()['attribute_value'])->toBe([
        ['attribute_id' => $size->id, 'value' => 'M', 'rows' => 2, 'ids' => [$first->id, $second->id]],
    ]);
});

test('describe() lists every duplicate group with its affected IDs', function () {
    dropVariantAttributeUniqueIndexes();
    $first = ProductVariant::factory()->create(['sku' => 'DUP-2']);
    $second = ProductVariant::factory()->create(['sku' => 'DUP-2']);

    $description = VariantAttributeDuplicateCheck::describe(VariantAttributeDuplicateCheck::find());

    expect($description)
        ->toContain('product_variants.sku = DUP-2 on 2 variants')
        ->toContain("ids {$first->id}, {$second->id}");
});

test('the command succeeds when there are no duplicates', function () {
    ProductVariant::factory()->create(['sku' => 'CLEAN-1']);

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain('No duplicate SKUs or attribute values found.')
        ->assertSuccessful();
});

test('the command fails and names the duplicates when any exist', function () {
    dropVariantAttributeUniqueIndexes();
    $first = ProductVariant::factory()->create(['sku' => 'DUP-3']);
    $second = ProductVariant::factory()->create(['sku' => 'DUP-3']);

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain("product_variants.sku = DUP-3 on 2 variants (ids {$first->id}, {$second->id})")
        ->assertFailed();
});

test('the command fails cleanly, without a stack trace, when the check cannot complete', function () {
    DB::shouldReceive('table')->andThrow(new RuntimeException('simulated connection failure'));

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain('Could not complete the duplicate check: simulated connection failure')
        ->assertFailed();
});

test('two different duplicate groups — a duplicate SKU and a duplicate attribute value — are both reported together', function () {
    dropVariantAttributeUniqueIndexes();
    $skuFirst = ProductVariant::factory()->create(['sku' => 'MULTI-SKU']);
    $skuSecond = ProductVariant::factory()->create(['sku' => 'MULTI-SKU']);
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $valueFirst = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L']);
    $valueSecond = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L']);

    $duplicates = VariantAttributeDuplicateCheck::find();

    expect($duplicates['sku'])->toBe([
        ['value' => 'MULTI-SKU', 'rows' => 2, 'ids' => [$skuFirst->id, $skuSecond->id]],
    ]);
    expect($duplicates['attribute_value'])->toBe([
        ['attribute_id' => $size->id, 'value' => 'L', 'rows' => 2, 'ids' => [$valueFirst->id, $valueSecond->id]],
    ]);

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain("product_variants.sku = MULTI-SKU on 2 variants (ids {$skuFirst->id}, {$skuSecond->id})")
        ->expectsOutputToContain("attribute_values.value = L on attribute_id {$size->id}, 2 rows (ids {$valueFirst->id}, {$valueSecond->id})")
        ->assertFailed();
});

test('a duplicate SKU group of three or more rows reports every row and the correct count', function () {
    dropVariantAttributeUniqueIndexes();
    $first = ProductVariant::factory()->create(['sku' => 'TRIPLE-SKU']);
    $second = ProductVariant::factory()->create(['sku' => 'TRIPLE-SKU']);
    $third = ProductVariant::factory()->create(['sku' => 'TRIPLE-SKU']);

    $duplicates = VariantAttributeDuplicateCheck::find();

    expect($duplicates['sku'])->toBe([
        ['value' => 'TRIPLE-SKU', 'rows' => 3, 'ids' => [$first->id, $second->id, $third->id]],
    ]);

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain("product_variants.sku = TRIPLE-SKU on 3 variants (ids {$first->id}, {$second->id}, {$third->id})")
        ->assertFailed();
});

test('a duplicate attribute-value group of three or more rows reports every row and the correct count', function () {
    dropVariantAttributeUniqueIndexes();
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $first = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);
    $second = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);
    $third = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);

    $duplicates = VariantAttributeDuplicateCheck::find();

    expect($duplicates['attribute_value'])->toBe([
        ['attribute_id' => $size->id, 'value' => 'XL', 'rows' => 3, 'ids' => [$first->id, $second->id, $third->id]],
    ]);

    $this->artisan('catalog:check-variant-attribute-duplicates')
        ->expectsOutputToContain("attribute_values.value = XL on attribute_id {$size->id}, 3 rows (ids {$first->id}, {$second->id}, {$third->id})")
        ->assertFailed();
});
