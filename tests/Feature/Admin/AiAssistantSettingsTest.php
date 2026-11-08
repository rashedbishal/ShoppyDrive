<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use App\Support\AiAssistantConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('the mother admin can save a key and turn the assistant on', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'AIza-test-key', 'enabled' => true, 'model' => 'llama-3.1-8b-instant'])
        ->assertRedirect();

    expect(Setting::where('key', 'ai_assistant_api_key')->first()?->value)->toBe('AIza-test-key')
        ->and((bool) Setting::where('key', 'ai_assistant_enabled')->first()?->value)->toBeTrue();
});

test('leaving the key field blank keeps the existing key', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'original-key', 'group' => 'ai_assistant']);

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => '', 'enabled' => false, 'model' => 'llama-3.1-8b-instant'])
        ->assertRedirect();

    expect(Setting::where('key', 'ai_assistant_api_key')->first()?->value)->toBe('original-key')
        ->and((bool) Setting::where('key', 'ai_assistant_enabled')->first()?->value)->toBeFalse();
});

test('the chosen model is saved and only an allow-listed model is accepted', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'a-key', 'enabled' => true, 'model' => 'llama-3.3-70b-versatile'])
        ->assertRedirect();

    expect(Setting::where('key', 'ai_assistant_model')->first()?->value)->toBe('llama-3.3-70b-versatile');

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'a-key', 'enabled' => true, 'model' => 'whisper-large-v3'])
        ->assertSessionHasErrors('model');
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

    $this->post(route('admin.settings.ai-assistant.update'), ['api_key' => 'super-secret-key', 'enabled' => true, 'model' => 'llama-3.1-8b-instant']);

    $entry = Activity::query()->where('description', 'like', '%updated the AI assistant settings%')->sole();

    expect($entry->description)->not->toContain('super-secret-key');
});

test('every candidate model is offered when no key is configured yet', function () {
    actingAs(createMotherAdmin(), 'admin');

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['availableModels'])->toBe(AiAssistantConfig::CANDIDATE_MODELS)
        ->and($props['selectedModelUnavailable'])->toBeFalse();
});

test('the model dropdown is narrowed to what Groq actually reports as available', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);

    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response([
            'data' => [['id' => 'llama-3.1-8b-instant'], ['id' => 'whisper-large-v3']],
        ]),
    ]);

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();

    expect($response->viewData('page')['props']['availableModels'])->toBe([
        'llama-3.1-8b-instant' => AiAssistantConfig::CANDIDATE_MODELS['llama-3.1-8b-instant'],
    ]);
});

test('the pre-selected model defaults to one this account can actually reach, not the hardcoded default', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);

    // This account's key doesn't have llama-3.1-8b-instant (the hardcoded
    // DEFAULT_MODEL) — only gpt-oss-20b, the exact situation that caused
    // the assistant to silently 404 until someone opened this page.
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-20b']]]),
    ]);

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();

    expect($response->viewData('page')['props']['selectedModel'])->toBe('openai/gpt-oss-20b');
});

test('an explicitly saved model choice is kept even if a Groq lookup hiccup momentarily omits it', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);
    Setting::updateOrCreate(['key' => 'ai_assistant_model'], ['value' => 'llama-3.3-70b-versatile', 'group' => 'ai_assistant']);

    Http::fake(['api.groq.com/openai/v1/models' => Http::response(['error' => 'nope'], 500)]);

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();

    expect($response->viewData('page')['props']['selectedModel'])->toBe('llama-3.3-70b-versatile')
        ->and($response->viewData('page')['props']['selectedModelUnavailable'])->toBeFalse();
});

test('the unavailable-model warning fires when the saved model has genuinely dropped out of Groq\'s live list', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);
    Setting::updateOrCreate(['key' => 'ai_assistant_model'], ['value' => 'llama-3.3-70b-versatile', 'group' => 'ai_assistant']);

    // A real, narrower list that comes back successfully but no longer
    // includes the saved model — e.g. Groq retired it, or revoked this
    // account's access — as opposed to the lookup failing outright above.
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-20b']]]),
    ]);

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['selectedModel'])->toBe('llama-3.3-70b-versatile')
        ->and($props['selectedModelUnavailable'])->toBeTrue()
        ->and($props['selectedModelLabel'])->toBe(AiAssistantConfig::CANDIDATE_MODELS['llama-3.3-70b-versatile']);
});

test('the full candidate list is offered when Groq cannot be reached for the model list', function () {
    actingAs(createMotherAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'ai_assistant_api_key'], ['value' => 'a-key', 'group' => 'ai_assistant']);

    Http::fake(['api.groq.com/openai/v1/models' => Http::response(['error' => 'nope'], 500)]);

    $response = $this->get(route('admin.settings.ai-assistant.index'))->assertOk();

    expect($response->viewData('page')['props']['availableModels'])->toBe(AiAssistantConfig::CANDIDATE_MODELS);
});
