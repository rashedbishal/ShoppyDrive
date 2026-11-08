<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Rules\BalancedScriptTags;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin › Marketing & Tracking: two independently-gated forms on one page.
 * Custom Header/Footer code runs in every visitor's browser — a materially
 * bigger capability than anything else here — so saving it is hardcoded
 * mother-only, the same pattern as SecuritySettingsController::update(), not
 * delegable via the Permissions matrix. The Facebook Conversions API fields
 * are plain config values with no code-execution risk, gated normally like
 * any other delegable settings page.
 */
class MarketingSettingsController extends Controller
{
    private const CUSTOM_CODE_FIELDS = ['custom_header_code', 'custom_footer_code'];

    private const TRACKING_FIELDS = [
        'google_analytics_id',
        FacebookConversionsApiConfig::PIXEL_ID_KEY,
        FacebookConversionsApiConfig::ACCESS_TOKEN_KEY,
        FacebookConversionsApiConfig::TEST_EVENT_CODE_KEY,
        FacebookConversionsApiConfig::ENABLED_KEY,
    ];

    public function edit(Request $request): Response
    {
        $settings = Setting::whereIn('key', [...self::CUSTOM_CODE_FIELDS, ...self::TRACKING_FIELDS])
            ->get()
            ->keyBy('key')
            ->map(fn (Setting $setting) => $setting->value);

        $token = (string) ($settings[FacebookConversionsApiConfig::ACCESS_TOKEN_KEY] ?? '');

        return Inertia::render('admin/settings/marketing', [
            'settings' => [
                'custom_header_code' => $settings['custom_header_code'] ?? '',
                'custom_footer_code' => $settings['custom_footer_code'] ?? '',
                'google_analytics_id' => $settings['google_analytics_id'] ?? '',
                'facebook_pixel_id' => $settings[FacebookConversionsApiConfig::PIXEL_ID_KEY] ?? '',
                'facebook_conversions_api_test_event_code' => $settings[FacebookConversionsApiConfig::TEST_EVENT_CODE_KEY] ?? '',
                'facebook_conversions_api_enabled' => (bool) ($settings[FacebookConversionsApiConfig::ENABLED_KEY] ?? false),
                'facebook_conversions_api_token_masked' => $this->maskToken($token),
                'has_facebook_conversions_api_token' => $token !== '',
            ],
            'canEditCustomCode' => (bool) $request->user('admin')?->is_mother,
        ]);
    }

    public function updateCustomCode(Request $request): RedirectResponse
    {
        if (! Auth::guard('admin')->user()?->is_mother) {
            return back()->with('error', 'Only the mother admin can change custom header/footer code.');
        }

        $validated = $request->validate([
            'custom_header_code' => ['nullable', 'string', 'max:20000', new BalancedScriptTags],
            'custom_footer_code' => ['nullable', 'string', 'max:20000', new BalancedScriptTags],
        ]);

        Setting::updateOrCreate(
            ['key' => 'custom_header_code'],
            ['value' => $validated['custom_header_code'] ?? null, 'group' => 'marketing'],
        );

        Setting::updateOrCreate(
            ['key' => 'custom_footer_code'],
            ['value' => $validated['custom_footer_code'] ?? null, 'group' => 'marketing'],
        );

        return back()->with('success', 'Custom header/footer code saved.');
    }

    public function updateFacebookConversionsApi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'google_analytics_id' => ['nullable', 'string', 'max:100'],
            'facebook_pixel_id' => ['nullable', 'string', 'regex:/^\d+$/', 'max:32'],
            'facebook_conversions_api_token' => ['nullable', 'string', 'max:255'],
            'facebook_conversions_api_test_event_code' => ['nullable', 'string', 'max:50'],
            'facebook_conversions_api_enabled' => ['required', 'boolean'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'google_analytics_id'],
            ['value' => $validated['google_analytics_id'] ?? null, 'group' => 'marketing'],
        );

        Setting::updateOrCreate(
            ['key' => FacebookConversionsApiConfig::PIXEL_ID_KEY],
            ['value' => $validated['facebook_pixel_id'] ?? null, 'group' => 'marketing'],
        );

        // Blank means "leave it as is" (the form's own placeholder says so)
        // — without this check, saving the switch alone would wipe the token.
        $token = trim((string) ($validated['facebook_conversions_api_token'] ?? ''));
        if ($token !== '') {
            Setting::updateOrCreate(
                ['key' => FacebookConversionsApiConfig::ACCESS_TOKEN_KEY],
                ['value' => $token, 'group' => 'marketing'],
            );
        }

        Setting::updateOrCreate(
            ['key' => FacebookConversionsApiConfig::TEST_EVENT_CODE_KEY],
            ['value' => $validated['facebook_conversions_api_test_event_code'] ?? null, 'group' => 'marketing'],
        );

        Setting::updateOrCreate(
            ['key' => FacebookConversionsApiConfig::ENABLED_KEY],
            ['value' => (bool) $validated['facebook_conversions_api_enabled'], 'group' => 'marketing'],
        );

        return back()->with('success', 'Facebook Conversions API settings saved.');
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 6).substr($token, -6);
    }
}
