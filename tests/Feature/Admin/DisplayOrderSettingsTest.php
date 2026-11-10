<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use App\Support\DisplayOrderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('every scenario defaults to descending when nothing has been saved yet', function () {
    expect(DisplayOrderSettings::orderFor('featured_products'))->toBe('descending');

    foreach (DisplayOrderSettings::all() as $order) {
        expect($order)->toBe('descending');
    }
});

test('the mother admin can view the settings page and see every scenario', function () {
    actingAs(createMotherAdmin(), 'admin');

    $response = $this->get(route('admin.settings.display-order.index'))->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['scenarios'])->toBe(DisplayOrderSettings::SCENARIOS)
        ->and($props['orders'])->toBe(DisplayOrderSettings::ORDERS)
        ->and(array_keys($props['values']))->toBe(array_keys(DisplayOrderSettings::SCENARIOS));
});

test('the mother admin can save a scenario to random and it is read back correctly', function () {
    actingAs(createMotherAdmin(), 'admin');

    $values = DisplayOrderSettings::all();
    $values['related_products'] = 'random';

    $this->post(route('admin.settings.display-order.update'), ['values' => $values])
        ->assertRedirect();

    expect(DisplayOrderSettings::orderFor('related_products'))->toBe('random')
        ->and(DisplayOrderSettings::orderFor('wishlist'))->toBe('descending');
});

test('an unknown scenario key or an invalid order value is rejected', function () {
    actingAs(createMotherAdmin(), 'admin');

    $values = DisplayOrderSettings::all();
    $values['not_a_real_scenario'] = 'descending';

    $this->post(route('admin.settings.display-order.update'), ['values' => $values])
        ->assertSessionHasNoErrors(); // the extra key is simply ignored, not an error

    expect(Setting::where('key', 'display_order.not_a_real_scenario')->exists())->toBeFalse();

    $badValue = DisplayOrderSettings::all();
    $badValue['related_products'] = 'ascending'; // not one of the two allowed orders

    $this->post(route('admin.settings.display-order.update'), ['values' => $badValue])
        ->assertSessionHasErrors('values.related_products');
});

test('saving is recorded in the activity log', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->post(route('admin.settings.display-order.update'), ['values' => DisplayOrderSettings::all()]);

    expect(Activity::query()->where('description', 'like', '%updated the display order settings%')->exists())->toBeTrue();
});

test('an admin without the display_order permission cannot view or update it', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'display-order-blank@example.com', 'password' => bcrypt('password'),
    ]);
    actingAs($admin, 'admin');

    $this->get(route('admin.settings.display-order.index'))->assertForbidden();
    $this->post(route('admin.settings.display-order.update'), ['values' => DisplayOrderSettings::all()])->assertForbidden();
});

test('an admin granted only view access cannot save changes', function () {
    $admin = Admin::create([
        'name' => 'Viewer', 'email' => 'display-order-viewer@example.com', 'password' => bcrypt('password'),
        'permissions' => ['display_order' => ['view']],
    ]);
    actingAs($admin, 'admin');

    $this->get(route('admin.settings.display-order.index'))->assertOk();
    $this->post(route('admin.settings.display-order.update'), ['values' => DisplayOrderSettings::all()])->assertForbidden();
});
