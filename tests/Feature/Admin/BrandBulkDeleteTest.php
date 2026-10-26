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

test('bulk delete requires ids', function () {
    actingAs($this->admin, 'admin')
        ->delete(route('admin.brands.destroyMany'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
