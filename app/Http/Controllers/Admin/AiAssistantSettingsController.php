<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantSettingsController extends Controller
{
    private const KEY_SETTING = 'ai_assistant_api_key';

    private const ENABLED_SETTING = 'ai_assistant_enabled';

    public function index(Request $request): Response
    {
        $apiKey = (string) (Setting::where('key', self::KEY_SETTING)->first()?->value ?? '');
        $canReveal = $request->user('admin')?->canAccess('ai_assistant', 'update') ?? false;

        $props = [
            'maskedKey' => $this->maskKey($apiKey),
            'enabled' => (bool) (Setting::where('key', self::ENABLED_SETTING)->first()?->value ?? false),
            'hasKey' => $apiKey !== '',
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

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) updated the AI assistant settings.");

        return redirect()->route('admin.settings.ai-assistant.index')
            ->with('success', 'AI assistant settings saved.');
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
