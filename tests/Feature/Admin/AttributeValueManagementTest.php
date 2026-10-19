<?php

use App\Enums\AttributeType;
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

    $this->colorAttribute = Attribute::create(['name' => 'Color', 'type' => AttributeType::COLOR]);
    $this->sizeAttribute = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
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
    $response->assertSessionHas('error');
    expect(AttributeValue::find($value->id))->not->toBeNull();
});
