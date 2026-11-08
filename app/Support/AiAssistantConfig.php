<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * The Groq API key (used to run Llama 3.1 8B), the model it runs, and the
 * assistant's on/off switch all live in Setting (like every other
 * third-party credential in this app), never in .env — see
 * Admin\AiAssistantSettingsController.
 */
class AiAssistantConfig
{
    public const API_KEY_KEY = 'ai_assistant_api_key';

    public const ENABLED_KEY = 'ai_assistant_enabled';

    public const MODEL_KEY = 'ai_assistant_model';

    public const DEFAULT_MODEL = 'llama-3.1-8b-instant';

    /**
     * The only models Admin's dropdown offers, regardless of what Groq's
     * account-level model list (AiAssistantService::availableModels())
     * returns. Groq's catalog also includes audio, TTS and safety models —
     * tool calling doesn't apply to those, and search_products/
     * check_product_availability/lookup_order all depend on it, so picking
     * one would silently break the assistant rather than fail loudly.
     *
     * @var array<string, string> model id => display label
     */
    public const CANDIDATE_MODELS = [
        'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant — fastest, most generous free tier',
        'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile — stronger reasoning, smaller free-tier quota',
        'openai/gpt-oss-20b' => 'GPT-OSS 20B',
        'openai/gpt-oss-120b' => 'GPT-OSS 120B — stronger reasoning, smaller free-tier quota',
        'qwen/qwen3.8-27b' => 'Qwen3 27B',
    ];

    public static function apiKey(): string
    {
        return (string) (Setting::where('key', self::API_KEY_KEY)->first()?->value ?? '');
    }

    public static function isEnabled(): bool
    {
        return (bool) (Setting::where('key', self::ENABLED_KEY)->first()?->value ?? false);
    }

    /**
     * Falls back to DEFAULT_MODEL if unset or if the stored value somehow
     * isn't one of CANDIDATE_MODELS (sanitize() on the write side is what's
     * meant to guarantee that, but a read path should never trust it blindly).
     */
    public static function model(): string
    {
        $model = (string) (Setting::where('key', self::MODEL_KEY)->first()?->value ?? '');

        return array_key_exists($model, self::CANDIDATE_MODELS) ? $model : self::DEFAULT_MODEL;
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
