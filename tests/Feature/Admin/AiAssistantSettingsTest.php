<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('the mother admin can save a key and turn the assistant on', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'AIza-test-key', 'enabled' => true])
        ->assertRedirect();

    expect(Setting::where('key', 'ai_assistant_api_key')->first()?->value)->toBe('AIza-test-key')
        ->and((bool) Setting::where('key', 'ai_assistant_enabled')->first()?->value)->toBeTrue();
});

test('leaving the key field blank keeps the existing key', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'original-key', 'group' => 'ai_assistant']);

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'original-key', 'enabled' => false]);

    expect(Setting::where('key', 'ai_assistant_api_key')->first()?->value)->toBe('original-key');
});

test('a view-only admin does not receive the full key', function () {
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'secret-key', 'group' => 'ai_assistant']);
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'ai-viewer@example.com', 'password' => bcrypt('password'),
        'permissions' => ['ai_assistant' => ['view']],
    ]);
    actingAs($admin, 'admin');

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();

    expect(array_key_exists('fullKey', $response->viewData('page')['props']))->toBeFalse();
});

test('saving the key is recorded in the activity log without the key itself', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'super-secret-key', 'enabled' => true]);

    $entry = Activity::query()->where('description', 'like', '%updated the AI assistant settings%')->sole();

    expect($entry->description)->not->toContain('super-secret-key');
});
