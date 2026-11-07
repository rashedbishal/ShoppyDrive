<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * The Gemini API key and the assistant's on/off switch both live in
 * Setting (like every other third-party credential in this app), never
 * in .env — see Admin\AiAssistantSettingsController.
 */
class AiAssistantConfig
{
    public const API_KEY_KEY = 'ai_assistant_api_key';

    public const ENABLED_KEY = 'ai_assistant_enabled';

    public static function apiKey(): string
    {
        return (string) (Setting::where('key', self::API_KEY_KEY)->first()?->value ?? '');
    }

    public static function isEnabled(): bool
    {
        return (bool) (Setting::where('key', self::ENABLED_KEY)->first()?->value ?? false);
    }

    /**
     * Whether the assistant can actually be used right now — toggled on
     * in Admin AND has a key configured. Either one missing fails closed.
     */
    public static function isAvailable(): bool
    {
        return self::isEnabled() && self::apiKey() !== '';
    }
}
