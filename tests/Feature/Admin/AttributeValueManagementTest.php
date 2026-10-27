<?php

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->colorAttribute = Attribute::firstWhere('name', 'Color');
    $this->sizeAttribute = Attribute::firstWhere('name', 'Size');
});

test('admin can view the attributes index with color and size grouped', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);
    AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->get(route('admin.attributes.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('admin/attributes/index')
        ->has('attributes', 2)
    );
});

test('admin can add a color value with a hex code', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->colorAttribute->id,
        'value' => 'Navy Blue',
        'color_hex' => '#001f54',
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    expect(AttributeValue::where('value', 'Navy Blue')->first()->color_hex)->toBe('#001f54');
});

test('color value requires a hex code', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->colorAttribute->id,
        'value' => 'Navy Blue',
        'color_hex' => '',
    ]);

    $response->assertInvalid(['color_hex']);
});

test('size value does not require a hex code', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->sizeAttribute->id,
        'value' => 'XL',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    expect(AttributeValue::where('value', 'XL')->exists())->toBeTrue();
});

test('admin can update an attribute value', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->put(route('admin.attribute-values.update', $value), [
        'attribute_id' => $this->sizeAttribute->id,
        'value' => 'Medium',
        'sort_order' => 1,
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    expect($value->refresh()->value)->toBe('Medium');
});

test('admin can update an attribute value without resubmitting attribute_id', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->put(route('admin.attribute-values.update', $value), [
        'value' => 'Medium',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    expect($value->refresh()->value)->toBe('Medium');
});

test('admin can reorder attribute values', function () {
    actingAs($this->admin, 'admin');

    $first = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'S', 'sort_order' => 0]);
    $second = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M', 'sort_order' => 1]);
    $third = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'L', 'sort_order' => 2]);

    $response = $this->post(route('admin.attribute-values.reorder'), [
        'ids' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    expect($third->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
    expect($second->refresh()->sort_order)->toBe(2);
});

test('admin can delete an unused attribute value', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->delete(route('admin.attribute-values.destroy', $value));

    $response->assertRedirect(route('admin.attributes.index'));
    expect(AttributeValue::find($value->id))->toBeNull();
});

test('deleting an attribute value in use by a product is blocked', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);
    $product = Product::factory()->create();
    $product->attributeValues()->attach($value->id);

    $response = $this->delete(route('admin.attribute-values.destroy', $value));

    $response->assertRedirect(route('admin.attributes.index'));
    $response->assertSessionHas('inertia.flash_data', fn ($flash) => ($flash['toast']['type'] ?? null) === 'error');
    expect(AttributeValue::find($value->id))->not->toBeNull();
});

test('a duplicate size value is rejected', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'XL']);

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->sizeAttribute->id,
        'value' => 'XL',
    ]);

    $response->assertInvalid(['value']);
    expect(AttributeValue::where('attribute_id', $this->sizeAttribute->id)->where('value', 'XL')->count())->toBe(1);
});

test('the same size value is allowed to coexist with an identically named color', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'Gold']);

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->colorAttribute->id,
        'value' => 'Gold',
        'color_hex' => '#ffd700',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    $response->assertSessionDoesntHaveErrors();
});

test('renaming a size value to its own current name is not rejected as a duplicate', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->put(route('admin.attribute-values.update', $value), [
        'value' => 'M',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    $response->assertSessionDoesntHaveErrors();
});

test('renaming a size value to one already used by another size is rejected', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'XL']);
    $value = AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'M']);

    $response = $this->put(route('admin.attribute-values.update', $value), [
        'value' => 'XL',
    ]);

    $response->assertInvalid(['value']);
    expect($value->refresh()->value)->toBe('M');
});

test('a duplicate color name is rejected', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->colorAttribute->id,
        'value' => 'Red',
        'color_hex' => '#cc0000',
    ]);

    $response->assertInvalid(['value']);
});

test('a duplicate color hex code is rejected even with a different name', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->colorAttribute->id,
        'value' => 'Fire Red',
        'color_hex' => '#ff0000',
    ]);

    $response->assertInvalid(['color_hex']);
});

test('adding a second size value is not blocked by the shared default color_hex', function () {
    actingAs($this->admin, 'admin');

    AttributeValue::create(['attribute_id' => $this->sizeAttribute->id, 'value' => 'S', 'color_hex' => '#c8102e']);

    $response = $this->post(route('admin.attribute-values.store'), [
        'attribute_id' => $this->sizeAttribute->id,
        'value' => 'L',
        'color_hex' => '#c8102e',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    $response->assertSessionDoesntHaveErrors();
    expect(AttributeValue::where('attribute_id', $this->sizeAttribute->id)->where('value', 'L')->exists())->toBeTrue();
});

test('editing a color to reuse its own current hex code is not rejected as a duplicate', function () {
    actingAs($this->admin, 'admin');

    $value = AttributeValue::create(['attribute_id' => $this->colorAttribute->id, 'value' => 'Red', 'color_hex' => '#ff0000']);

    $response = $this->put(route('admin.attribute-values.update', $value), [
        'value' => 'Bright Red',
        'color_hex' => '#ff0000',
    ]);

    $response->assertRedirect(route('admin.attributes.index'));
    $response->assertSessionDoesntHaveErrors();
    expect($value->refresh()->value)->toBe('Bright Red');
});
