<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::create(['key' => 'logo', 'value' => '/storage/logos/brand.png', 'group' => 'general']);
    Setting::create(['key' => 'loading_logo', 'value' => '/storage/logos/loader.webp', 'group' => 'general']);
    Setting::create(['key' => 'loading_ring_size', 'value' => 300, 'group' => 'general']);
});

test('admin pages that send their own settings prop still get the brand for the logo and loader', function (string $routeName) {
    actingAs(createAdmin(), 'admin')
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('brand.logo', '/storage/logos/brand.png')
            ->where('brand.loading_logo', '/storage/logos/loader.webp')
            ->where('brand.loading_ring_size', 300)
            ->where('brand.site_name', fn ($name) => is_string($name) && $name !== ''));
})->with([
    'Marketing & Tracking' => 'admin.settings.marketing.edit',
    'General settings' => 'admin.settings.edit',
    'Social login' => 'admin.settings.social',
    'Courier' => 'admin.settings.courier',
]);

test('storefront pages get the brand too', function () {
    $this->get('/order/track')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('brand.logo', '/storage/logos/brand.png')
            ->where('brand.loading_logo', '/storage/logos/loader.webp'));
});

test('the brand carries no other settings', function () {
    Setting::create(['key' => 'contact_phone', 'value' => '01700000000', 'group' => 'contact']);

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where(
        'brand',
        fn ($brand): bool => collect($brand)->keys()->sort()->values()->all() === [
            'loading_logo', 'loading_logo_size', 'loading_ring_size', 'loading_ring_thickness',
            'loading_spin_seconds', 'logo', 'site_name',
        ],
    ));
});
