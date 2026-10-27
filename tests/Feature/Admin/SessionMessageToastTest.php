<?php

use App\Models\Admin;
use App\Models\Category;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin',
        'email' => 'toast-admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('an admin session message is delivered as a toast', function (string $type) {
    actingAs($this->admin, 'admin');

    $this->withSession([$type => 'Brand saved.'])
        ->get(route('admin.brands.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => $type, 'message' => 'Brand saved.']));
})->with(['success', 'error', 'info']);

test('a toast the controller already flashed is not overwritten', function () {
    actingAs($this->admin, 'admin');

    $this->withSession([
        'success' => 'Session message.',
        'inertia.flash_data' => ['toast' => ['type' => 'error', 'message' => 'Controller toast.']],
    ])
        ->get(route('admin.brands.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast', ['type' => 'error', 'message' => 'Controller toast.']));
});

test('a real admin action that uses a session message ends up as a toast', function () {
    actingAs($this->admin, 'admin');
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);

    $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

    $this->get(route('admin.categories.index'))
        ->assertInertia(fn (Assert $page) => $page->hasFlash('toast.type', 'success'));
});

test('storefront pages are left alone', function () {
    $this->withSession(['success' => 'Saved.'])
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->missingFlash('toast'));
});
