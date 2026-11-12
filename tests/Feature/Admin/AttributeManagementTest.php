<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Admin › Attributes: staff create attributes (Set, Material, …) that
 * products build variants from, rename them, choose whether each value can
 * have its own image, and delete them only while nothing uses them.
 */
beforeEach(function () {
    $this->admin = createAdmin();
});

function setAttributeWithValues(array $values = []): Attribute
{
    $attribute = Attribute::create(['name' => 'Set', 'type' => AttributeType::SELECT]);

    foreach ($values as $value) {
        AttributeValue::create(['attribute_id' => $attribute->id, 'value' => $value]);
    }

    return $attribute->fresh('values');
}

test('an admin creates an attribute with images per value', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.attributes.store'), ['name' => 'Material', 'type' => 'select', 'has_images' => true])
        ->assertRedirect(route('admin.attributes.index'));

    $this->assertDatabaseHas('attributes', ['name' => 'Material', 'type' => 'select', 'has_images' => true]);
});

test('an attribute name already in use, in any case, is rejected', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.attributes.store'), ['name' => ' color ', 'type' => 'select', 'has_images' => false])
        ->assertSessionHasErrors(['name' => 'An attribute with this name already exists.']);

    expect(Attribute::whereRaw('LOWER(name) = ?', ['color'])->count())->toBe(1);
});

test('an attribute can only be text options or colour swatches', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.attributes.store'), ['name' => 'Notes', 'type' => 'text', 'has_images' => false])
        ->assertSessionHasErrors('type');
});

test('the type can change while an attribute has no values, but not after', function () {
    $empty = Attribute::create(['name' => 'Finish', 'type' => AttributeType::SELECT]);
    $withValues = setAttributeWithValues(['6pcs']);

    actingAs($this->admin, 'admin')
        ->put(route('admin.attributes.update', $empty), ['name' => 'Finish', 'type' => 'color', 'has_images' => false])
        ->assertSessionHasNoErrors();
    expect($empty->fresh()->type)->toBe(AttributeType::COLOR);

    actingAs($this->admin, 'admin')
        ->put(route('admin.attributes.update', $withValues), ['name' => 'Set', 'type' => 'color', 'has_images' => false])
        ->assertSessionHasErrors(['type' => "The type can't change once the attribute has values."]);
    expect($withValues->fresh()->type)->toBe(AttributeType::SELECT);
});

test('renaming an attribute or a value rewrites the labels of variants using it', function () {
    $set = setAttributeWithValues(['6pcs']);
    $sixPieces = $set->values->first();
    $product = Product::factory()->create(['variant_attribute_ids' => [$set->id]]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'attribute_value_ids' => [$sixPieces->id],
        'attributes' => ['Set' => '6pcs'],
    ]);

    actingAs($this->admin, 'admin')
        ->put(route('admin.attributes.update', $set), ['name' => 'Pack', 'type' => 'select', 'has_images' => false])
        ->assertSessionHasNoErrors();
    expect($variant->fresh()->attributes)->toBe(['Pack' => '6pcs']);

    actingAs($this->admin, 'admin')
        ->put(route('admin.attribute-values.update', $sixPieces), ['value' => '6 pieces', 'sort_order' => 0])
        ->assertSessionHasNoErrors();
    expect($variant->fresh()->attributes)->toBe(['Pack' => '6 pieces']);
});

test('an attribute no product uses is deleted with its values', function () {
    $set = setAttributeWithValues(['6pcs', '12pcs']);

    actingAs($this->admin, 'admin')
        ->delete(route('admin.attributes.destroy', $set))
        ->assertRedirect(route('admin.attributes.index'));

    $this->assertModelMissing($set);
    expect(AttributeValue::where('attribute_id', $set->id)->count())->toBe(0);
});

test('an attribute a product builds variants from cannot be deleted', function () {
    $set = setAttributeWithValues(['6pcs']);
    Product::factory()->create(['variant_attribute_ids' => [$set->id]]);

    actingAs($this->admin, 'admin')->delete(route('admin.attributes.destroy', $set));

    $this->assertModelExists($set);
});

test('an attribute whose value a retired variant still uses cannot be deleted', function () {
    $set = setAttributeWithValues(['6pcs']);
    ProductVariant::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'attribute_value_ids' => [$set->values->first()->id],
    ]);

    actingAs($this->admin, 'admin')->delete(route('admin.attributes.destroy', $set));

    $this->assertModelExists($set);
});

test('a value a custom-attribute variant uses cannot be deleted', function () {
    $set = setAttributeWithValues(['6pcs']);
    ProductVariant::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'attribute_value_ids' => [$set->values->first()->id],
    ]);

    actingAs($this->admin, 'admin')->delete(route('admin.attribute-values.destroy', $set->values->first()));

    $this->assertModelExists($set->values->first());
});

test('the attributes page tells which attributes are in use', function () {
    $used = setAttributeWithValues(['6pcs']);
    Product::factory()->create(['variant_attribute_ids' => [$used->id]]);

    actingAs($this->admin, 'admin')->get(route('admin.attributes.index'))
        ->assertInertia(fn ($page) => $page
            ->where('attributes', fn ($attributes) => collect($attributes)->firstWhere('name', 'Set')['in_use'] === true
                && collect($attributes)->firstWhere('name', 'Size')['in_use'] === false));
});

test('an admin without attribute permissions cannot create attributes', function () {
    $staff = createAdmin(['permissions' => ['attributes' => ['view']]]);

    actingAs($staff, 'admin')
        ->post(route('admin.attributes.store'), ['name' => 'Material', 'type' => 'select', 'has_images' => false])
        ->assertForbidden();

    $this->assertDatabaseMissing('attributes', ['name' => 'Material']);
});
