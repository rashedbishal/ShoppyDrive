<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * The Facebook Pixel ID, Conversions API access token, optional test event
 * code, and the integration's on/off switch all live in Setting (like every
 * other third-party credential in this app), never in .env — see
 * Admin\MarketingSettingsController. Modeled on AiAssistantConfig.
 */
class FacebookConversionsApiConfig
{
    public const PIXEL_ID_KEY = 'facebook_pixel_id';

    public const ACCESS_TOKEN_KEY = 'facebook_conversions_api_token';

    public const TEST_EVENT_CODE_KEY = 'facebook_conversions_api_test_event_code';

    public const ENABLED_KEY = 'facebook_conversions_api_enabled';

    public static function pixelId(): string
    {
        return (string) (Setting::where('key', self::PIXEL_ID_KEY)->first()?->value ?? '');
    }

    public static function accessToken(): string
    {
        return (string) (Setting::where('key', self::ACCESS_TOKEN_KEY)->first()?->value ?? '');
    }

    public static function testEventCode(): ?string
    {
        $code = (string) (Setting::where('key', self::TEST_EVENT_CODE_KEY)->first()?->value ?? '');

        return $code === '' ? null : $code;
    }

    public static function isEnabled(): bool
    {
        return (bool) (Setting::where('key', self::ENABLED_KEY)->first()?->value ?? false);
    }

    /**
     * Whether a Purchase event can actually be sent right now — toggled on
     * in Admin AND has both a pixel id and an access token configured.
     * Any one missing fails closed.
     */
    public static function isAvailable(): bool
    {
        return self::isEnabled() && self::pixelId() !== '' && self::accessToken() !== '';
    }
}
