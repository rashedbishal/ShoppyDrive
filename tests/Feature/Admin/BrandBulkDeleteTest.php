<?php

use App\Models\Admin;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Staff Admin',
        'email' => 'staff@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can bulk delete selected brands', function () {
    $brands = Brand::factory()->count(3)->create();
    $kept = Brand::factory()->create();

    actingAs($this->admin, 'admin')
        ->delete(route('admin.brands.destroyMany'), ['ids' => $brands->pluck('id')->all()])
        ->assertRedirect(route('admin.brands.index'))
        ->assertSessionHas('success', '3 brand(s) deleted.');

    expect(Brand::pluck('id')->all())->toBe([$kept->id]);
});

test('admin can bulk deactivate and activate selected brands', function () {
    $brands = Brand::factory()->count(2)->create(['is_active' => true]);
    $untouched = Brand::factory()->create(['is_active' => true]);
    $ids = $brands->pluck('id')->all();

    actingAs($this->admin, 'admin')
        ->patch(route('admin.brands.updateStatusMany'), ['ids' => $ids, 'active' => false])
        ->assertRedirect(route('admin.brands.index'))
        ->assertSessionHas('success', '2 brand(s) deactivated.');

    expect(Brand::whereIn('id', $ids)->where('is_active', false)->count())->toBe(2)
        ->and($untouched->fresh()->is_active)->toBeTrue();

    actingAs($this->admin, 'admin')
        ->patch(route('admin.brands.updateStatusMany'), ['ids' => $ids, 'active' => true])
        ->assertSessionHas('success', '2 brand(s) activated.');

    expect(Brand::whereIn('id', $ids)->where('is_active', true)->count())->toBe(2);
});

test('bulk status update requires an active flag', function () {
    $brand = Brand::factory()->create();

    actingAs($this->admin, 'admin')
        ->patch(route('admin.brands.updateStatusMany'), ['ids' => [$brand->id]])
        ->assertSessionHasErrors('active');
});

test('bulk delete requires ids', function () {
    actingAs($this->admin, 'admin')
        ->delete(route('admin.brands.destroyMany'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
