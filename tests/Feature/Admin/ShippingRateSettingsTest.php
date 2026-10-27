<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('shipping rates fall back to the original hardcoded defaults when not configured', function () {
    expect(Setting::shippingRateFor('Dhaka', 'Inside Dhaka City Corporation'))->toBe(60.0);
    expect(Setting::shippingRateFor('Dhaka', 'Outside Dhaka City Corporation'))->toBe(120.0);
    expect(Setting::shippingRateFor('Chattogram', 'Chattogram'))->toBe(120.0);
});

test('admin can configure the inside and outside dhaka delivery charges from the courier config page', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-shipping@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'shipping_rate_inside_dhaka' => 70,
        'shipping_rate_outside_dhaka' => 150,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect((float) Setting::where('key', 'shipping_rate_inside_dhaka')->first()->value)->toBe(70.0);
    expect((float) Setting::where('key', 'shipping_rate_outside_dhaka')->first()->value)->toBe(150.0);
    expect(Setting::shippingRateFor('Dhaka', 'Inside Dhaka City Corporation'))->toBe(70.0);
    expect(Setting::shippingRateFor('Dhaka', 'Outside Dhaka City Corporation'))->toBe(150.0);
});

test('a negative delivery charge is rejected', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-shipping-invalid@example.com',
        'password' => bcrypt('password'),
    ]);

    actingAs($admin, 'admin');

    $response = $this->put(route('admin.settings.update'), [
        'shipping_rate_inside_dhaka' => -10,
    ]);

    $response->assertSessionHasErrors('shipping_rate_inside_dhaka');
});
