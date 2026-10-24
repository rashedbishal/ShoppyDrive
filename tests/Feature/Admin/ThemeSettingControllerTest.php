<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can view the theme settings page', function () {
    actingAs($this->admin, 'admin');

    $response = $this->get(route('admin.settings.theme'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/settings/theme'));
});

test('admin can save a global theme', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'global',
        'global_theme' => 'neomorphism',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect(Setting::where('key', 'theme_config')->first()->value)->toBe([
        'mode' => 'global',
        'global_theme' => 'neomorphism',
    ]);
});

test('rejects an invalid theme name', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'global',
        'global_theme' => 'brutalist',
    ]);

    $response->assertSessionHasErrors('global_theme');
});

test('admin can save an a/b test configuration', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'ab_test',
        'ab_test' => [
            'variants' => [
                ['theme' => 'default', 'weight' => 50],
                ['theme' => 'glassmorphism', 'weight' => 50],
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();
});

test('rejects duplicate themes across a/b variants', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'ab_test',
        'ab_test' => [
            'variants' => [
                ['theme' => 'default', 'weight' => 50],
                ['theme' => 'default', 'weight' => 50],
            ],
        ],
    ]);

    $response->assertSessionHasErrors('ab_test.variants');
});

test('rejects a zero weight a/b variant', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'ab_test',
        'ab_test' => [
            'variants' => [
                ['theme' => 'default', 'weight' => 0],
            ],
        ],
    ]);

    $response->assertSessionHasErrors('ab_test.variants.0.weight');
});

test('admin can save a region map', function () {
    actingAs($this->admin, 'admin');

    $regionMap = [
        'Dhaka' => 'glassmorphism',
        'Chattogram' => 'neomorphism',
        'Khulna' => 'default',
        'Rajshahi' => 'default',
        'Barishal' => 'default',
        'Sylhet' => 'default',
        'Rangpur' => 'default',
        'Mymensingh' => 'default',
        'default' => 'default',
    ];

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'region',
        'region_map' => $regionMap,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect(Setting::where('key', 'theme_config')->first()->value['region_map'])->toBe($regionMap);
});

test('rejects an incomplete region map', function () {
    actingAs($this->admin, 'admin');

    $response = $this->put(route('admin.settings.theme.update'), [
        'mode' => 'region',
        'region_map' => [
            'Dhaka' => 'glassmorphism',
        ],
    ]);

    $response->assertSessionHasErrors('region_map');
});
