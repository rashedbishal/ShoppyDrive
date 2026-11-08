<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AiAssistantService;
use App\Support\AiAssistantConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantSettingsController extends Controller
{
    private const KEY_SETTING = AiAssistantConfig::API_KEY_KEY;

    private const ENABLED_SETTING = AiAssistantConfig::ENABLED_KEY;

    public function __construct(private readonly AiAssistantService $assistant) {}

    public function index(Request $request): Response
    {
        $apiKey = (string) (Setting::where('key', self::KEY_SETTING)->first()?->value ?? '');
        $canReveal = $request->user('admin')?->canAccess('ai_assistant', 'update') ?? false;
        $availableModels = $this->assistant->availableModels();
        $selectedModel = $this->resolveSelectedModel($availableModels);

        $props = [
            'maskedKey' => $this->maskKey($apiKey),
            'enabled' => (bool) (Setting::where('key', self::ENABLED_SETTING)->first()?->value ?? false),
            'hasKey' => $apiKey !== '',
            'selectedModel' => $selectedModel,
            'selectedModelLabel' => AiAssistantConfig::CANDIDATE_MODELS[$selectedModel] ?? $selectedModel,
            // False whenever availableModels() fell back to the full
            // CANDIDATE_MODELS list (no key yet, or the Groq lookup
            // itself failed) — only a real, narrower live list that
            // excludes the saved choice means it's genuinely unreachable.
            'selectedModelUnavailable' => ! array_key_exists($selectedModel, $availableModels),
            'availableModels' => $availableModels,
        ];

        if ($canReveal) {
            $props['fullKey'] = $apiKey;
        }

        return Inertia::render('admin/settings/ai-assistant', $props);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'model' => ['required', 'string', Rule::in(array_keys(AiAssistantConfig::CANDIDATE_MODELS))],
        ]);

        $apiKey = trim((string) ($validated['api_key'] ?? ''));

        // Blank means "leave it as is" (the form's own placeholder says so)
        // — without this check, saving the switch alone wiped the key.
        if ($apiKey !== '') {
            Setting::updateOrCreate(
                ['key' => self::KEY_SETTING],
                ['value' => $apiKey, 'group' => 'ai_assistant'],
            );
        }

        Setting::updateOrCreate(
            ['key' => self::ENABLED_SETTING],
            ['value' => (bool) $validated['enabled'], 'group' => 'ai_assistant'],
        );

        Setting::updateOrCreate(
            ['key' => AiAssistantConfig::MODEL_KEY],
            ['value' => $validated['model'], 'group' => 'ai_assistant'],
        );

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) updated the AI assistant settings.");

        return redirect()->route('admin.settings.ai-assistant.index')
            ->with('success', 'AI assistant settings saved.');
    }

    /**
     * An explicitly saved, still-valid choice is always respected as-is —
     * even if it doesn't appear in $availableModels right now, since that
     * list reflects this moment's Groq lookup and shouldn't silently
     * overrule a deliberate admin choice on a transient listing hiccup.
     * Only when nothing has been saved yet does this prefer a model this
     * account can actually reach over AiAssistantConfig::DEFAULT_MODEL,
     * which — as this account's own gpt-oss-only access just showed — is
     * not guaranteed to be reachable by every Groq account.
     *
     * @param  array<string, string>  $availableModels
     */
    private function resolveSelectedModel(array $availableModels): string
    {
        $stored = Setting::where('key', AiAssistantConfig::MODEL_KEY)->first()?->value;

        if (is_string($stored) && array_key_exists($stored, AiAssistantConfig::CANDIDATE_MODELS)) {
            return $stored;
        }

        return array_key_first($availableModels) ?? AiAssistantConfig::DEFAULT_MODEL;
    }

    private function maskKey(string $key): string
    {
        if ($key === '') {
            return '';
        }

        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return str_repeat('*', strlen($key) - 6).substr($key, -6);
    }
}
