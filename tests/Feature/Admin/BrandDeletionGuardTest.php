<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin-brand-guard@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('an unused brand can be deleted', function () {
    actingAs($this->admin, 'admin');

    $brand = Brand::factory()->create();

    $this->delete(route('admin.brands.destroy', $brand))
        ->assertRedirect(route('admin.brands.index'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Brand deleted.']);

    expect(Brand::find($brand->id))->toBeNull();
});

test('deleting a brand still attached to a product is blocked', function () {
    actingAs($this->admin, 'admin');

    $brand = Brand::factory()->create(['name' => 'Blocking Brand']);
    $product = Product::factory()->create(['brand_id' => $brand->id, 'name' => 'Branded Product']);

    $response = $this->delete(route('admin.brands.destroy', $brand));

    $response->assertRedirect(route('admin.brands.index'));
    $response->assertSessionHas('inertia.flash_data', function ($flash) use ($product) {
        $toast = $flash['toast'] ?? null;

        return $toast !== null
            && $toast['type'] === 'error'
            && str_contains($toast['message'], 'used by 1 product(s)')
            && ($toast['products'][0]['id'] ?? null) === $product->id;
    });

    expect(Brand::find($brand->id))->not->toBeNull();
    expect(Product::find($product->id))->not->toBeNull();
});

test('bulk deleting brands is blocked entirely when any selected brand is still in use', function () {
    actingAs($this->admin, 'admin');

    $freeBrand = Brand::factory()->create();
    $usedBrand = Brand::factory()->create(['name' => 'Used Brand']);
    $product = Product::factory()->create(['brand_id' => $usedBrand->id]);

    $response = $this->delete(route('admin.brands.destroyMany'), [
        'ids' => [$freeBrand->id, $usedBrand->id],
    ]);

    $response->assertRedirect(route('admin.brands.index'));
    $response->assertSessionHas('inertia.flash_data', function ($flash) use ($product) {
        $toast = $flash['toast'] ?? null;

        return $toast !== null
            && $toast['type'] === 'error'
            && ($toast['products'][0]['id'] ?? null) === $product->id;
    });

    // Neither brand was deleted — the free one wasn't silently removed either.
    expect(Brand::find($freeBrand->id))->not->toBeNull();
    expect(Brand::find($usedBrand->id))->not->toBeNull();
});
