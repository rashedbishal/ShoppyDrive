<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('unknown storefront urls render the themed 404 page with shared data', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/ErrorPage')
            ->where('status', 404)
            ->where('isAdminArea', false)
            ->has('settings'));
});

test('a missing model renders the themed 404 page', function () {
    $this->get('/product/no-such-product')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/ErrorPage')
            ->where('status', 404));
});

test('unknown admin urls render the 404 page flagged as the admin area', function () {
    $admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($admin, 'admin')
        ->get('/admin/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/ErrorPage')
            ->where('status', 404)
            ->where('isAdminArea', true));
});

test('forbidden responses render the themed 403 page', function () {
    Route::middleware('web')->get('/__test/forbidden', fn () => abort(403));

    $this->get('/__test/forbidden')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/ErrorPage')
            ->where('status', 403));
});

test('server errors render the themed 500 page when debug mode is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/__test/boom', fn () => throw new RuntimeException('Boom'));

    $this->get('/__test/boom')
        ->assertInternalServerError()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/ErrorPage')
            ->where('status', 500)
            ->missing('settings')
            // FE-005: `auth` isn't dropped to null here, it's absent
            // entirely — resources/js/app.tsx's navigation handler must
            // tell this apart from a real "you are a guest" response
            // (`auth.user: null`), or it wipes a logged-in customer's
            // cart on every server error.
            ->missing('auth'));
});

test('server errors keep the laravel debug screen when debug mode is on', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/__test/boom', fn () => throw new RuntimeException('Boom'));

    $this->get('/__test/boom')
        ->assertInternalServerError()
        ->assertSee('Boom');
});

test('json requests keep plain json error responses', function () {
    $this->getJson('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
