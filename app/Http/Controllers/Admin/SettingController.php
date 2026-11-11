<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SettingController extends Controller
{
    /**
     * `update()` is one endpoint shared by six sidebar sections (each page
     * posts only its own fields), so — unlike every other admin.can-gated
     * route — permission has to be checked per field group here instead of
     * at the route. Anything not listed falls back to the `settings` group.
     *
     * @var array<string, list<string>>
     */
    private const FIELD_GROUPS = [
        'social_config' => [
            'google_client_id', 'google_client_secret', 'google_redirect_uri',
            'facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri',
        ],
        'courier_config' => [
            'pathao_environment', 'pathao_client_id', 'pathao_client_secret',
            'pathao_username', 'pathao_password', 'pathao_store_id',
            'steadfast_api_key', 'steadfast_secret_key', 'steadfast_webhook_secret', 'steadfast_webhook_url',
            'shipping_rate_inside_dhaka', 'shipping_rate_outside_dhaka',
        ],
        'about_page' => [
            'about_hero_eyebrow', 'about_hero_title', 'about_hero_title_highlight', 'about_hero_description',
            'about_stats', 'about_mission_eyebrow', 'about_mission_title', 'about_mission_body',
            'about_mission_features', 'about_values', 'about_team', 'about_cta_title', 'about_cta_description',
        ],
        'contact_page' => [
            'contact_page_hero_eyebrow', 'contact_page_hero_title', 'contact_page_hero_title_highlight',
            'contact_page_hero_description', 'contact_page_support_hours',
            'contact_phone', 'contact_email', 'contact_address',
            // BIZ-034: seller identity shown in the storefront footer.
            'legal_business_name', 'trade_licence_number',
        ],
        'referral_program' => [
            'referral_enabled', 'referral_discount_type', 'referral_discount_value',
            'referral_min_order_amount', 'referral_reward_points', 'referral_reward_delay_days',
            'referral_unlock_order_count', 'referral_reward_cap_amount', 'referral_reward_cap_period',
            'loyalty_earn_rate_bdt_per_point', 'loyalty_earn_delay_days',
            'registration_bonus_points', 'review_bonus_points',
            'daily_login_base_points', 'daily_login_cap_days',
            'points_redeem_value_bdt', 'points_redeem_max_percent',
        ],
    ];

    /**
     * SEC-002: the fields admin/settings/edit.tsx (the general page) itself
     * reads — none of this is secret, but every settings page sending
     * `Setting::all()` meant an admin granted only one page (e.g.
     * about_page) received every courier/OAuth secret in the same
     * response, regardless of the permission check above. Each page
     * method below now passes its own list to settingsProp() instead.
     *
     * @var list<string>
     */
    private const GENERAL_FIELDS = [
        'site_name', 'logo', 'currency_symbol', 'guest_checkout_enabled',
        'order_notification_email', 'order_cancellation_email',
        'whatsapp_number', 'messenger_link', 'bkash_number', 'nagad_number', 'social_links',
    ];

    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/edit', [
            'settings' => $this->settingsProp(self::GENERAL_FIELDS),
        ]);
    }

    public function socialConfig(): Response
    {
        return Inertia::render('admin/settings/social', [
            'settings' => $this->settingsProp(self::FIELD_GROUPS['social_config']),
        ]);
    }

    public function courierConfig(): Response
    {
        return Inertia::render('admin/settings/courier', [
            'settings' => $this->settingsProp(self::FIELD_GROUPS['courier_config']),
        ]);
    }

    public function aboutPageConfig(): Response
    {
        return Inertia::render('admin/settings/about-page', [
            'settings' => $this->settingsProp(self::FIELD_GROUPS['about_page']),
        ]);
    }

    public function contactPageConfig(): Response
    {
        return Inertia::render('admin/settings/contact-page', [
            'settings' => $this->settingsProp(self::FIELD_GROUPS['contact_page']),
        ]);
    }

    public function referralConfig(): Response
    {
        return Inertia::render('admin/settings/referral', [
            'settings' => $this->settingsProp(self::FIELD_GROUPS['referral_program']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeFieldGroups($request);

        $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            // Shop-side recipients of the new-order and order-cancelled alerts
            // (see OrderNotificationRecipients); empty means the main admin.
            'order_notification_email' => ['nullable', 'email', 'max:255'],
            'order_cancellation_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'legal_business_name' => ['nullable', 'string', 'max:255'],
            'trade_licence_number' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'guest_checkout_enabled' => ['nullable', 'boolean'],
            'logo' => $request->hasFile('logo') ? ['image', 'max:2048'] : ['nullable'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'max:500'],
            'social_links.instagram' => ['nullable', 'string', 'max:500'],
            'social_links.twitter' => ['nullable', 'string', 'max:500'],
            'social_links.youtube' => ['nullable', 'string', 'max:500'],
            'social_links.tiktok' => ['nullable', 'string', 'max:500'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'messenger_link' => ['nullable', 'string', 'max:500'],
            'bkash_number' => ['nullable', 'string', 'max:20'],
            'nagad_number' => ['nullable', 'string', 'max:20'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_redirect_uri' => ['nullable', 'string', 'max:500'],
            'facebook_client_id' => ['nullable', 'string', 'max:255'],
            'facebook_client_secret' => ['nullable', 'string', 'max:255'],
            'facebook_redirect_uri' => ['nullable', 'string', 'max:500'],
            'pathao_environment' => ['nullable', 'in:sandbox,production'],
            'pathao_client_id' => ['nullable', 'string', 'max:255'],
            'pathao_client_secret' => ['nullable', 'string', 'max:255'],
            'pathao_username' => ['nullable', 'email', 'max:255'],
            'pathao_password' => ['nullable', 'string', 'max:255'],
            'pathao_store_id' => ['nullable', 'string', 'max:50'],
            'steadfast_api_key' => ['nullable', 'string', 'max:255'],
            'steadfast_secret_key' => ['nullable', 'string', 'max:255'],
            'steadfast_webhook_secret' => ['nullable', 'string', 'max:255'],
            'steadfast_webhook_url' => ['nullable', 'string', 'max:500'],
            'shipping_rate_inside_dhaka' => ['nullable', 'numeric', 'min:0'],
            'shipping_rate_outside_dhaka' => ['nullable', 'numeric', 'min:0'],
            'about_hero_eyebrow' => ['nullable', 'string', 'max:100'],
            'about_hero_title' => ['nullable', 'string', 'max:255'],
            'about_hero_title_highlight' => ['nullable', 'string', 'max:255'],
            'about_hero_description' => ['nullable', 'string', 'max:1000'],
            'about_stats' => ['nullable', 'array', 'max:8'],
            'about_stats.*.value' => ['nullable', 'string', 'max:50'],
            'about_stats.*.label' => ['nullable', 'string', 'max:100'],
            'about_mission_eyebrow' => ['nullable', 'string', 'max:100'],
            'about_mission_title' => ['nullable', 'string', 'max:255'],
            'about_mission_body' => ['nullable', 'string', 'max:20000'],
            'about_mission_features' => ['nullable', 'array', 'max:8'],
            'about_mission_features.*.icon' => ['nullable', 'string', 'max:100'],
            'about_mission_features.*.label' => ['nullable', 'string', 'max:100'],
            'about_values' => ['nullable', 'array', 'max:12'],
            'about_values.*.icon' => ['nullable', 'string', 'max:100'],
            'about_values.*.title' => ['nullable', 'string', 'max:100'],
            'about_values.*.description' => ['nullable', 'string', 'max:500'],
            'about_team' => ['nullable', 'array', 'max:24'],
            'about_team.*.name' => ['nullable', 'string', 'max:100'],
            'about_team.*.role' => ['nullable', 'string', 'max:100'],
            'about_team.*.initials' => ['nullable', 'string', 'max:4'],
            'about_cta_title' => ['nullable', 'string', 'max:255'],
            'about_cta_description' => ['nullable', 'string', 'max:500'],
            'contact_page_hero_eyebrow' => ['nullable', 'string', 'max:100'],
            'contact_page_hero_title' => ['nullable', 'string', 'max:255'],
            'contact_page_hero_title_highlight' => ['nullable', 'string', 'max:100'],
            'contact_page_hero_description' => ['nullable', 'string', 'max:500'],
            'contact_page_support_hours' => ['nullable', 'string', 'max:100'],
            'referral_enabled' => ['nullable', 'boolean'],
            'referral_discount_type' => ['nullable', 'in:percent,fixed'],
            'referral_discount_value' => ['nullable', 'numeric', 'min:0'],
            'referral_min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'referral_reward_points' => ['nullable', 'integer', 'min:0'],
            'referral_reward_delay_days' => ['nullable', 'integer', 'min:0'],
            'referral_unlock_order_count' => ['nullable', 'integer', 'min:0'],
            'referral_reward_cap_amount' => ['nullable', 'numeric', 'min:0'],
            'referral_reward_cap_period' => ['nullable', 'in:week,month'],
            'loyalty_earn_rate_bdt_per_point' => ['nullable', 'numeric', 'min:0.01'],
            'loyalty_earn_delay_days' => ['nullable', 'integer', 'min:0'],
            'registration_bonus_points' => ['nullable', 'integer', 'min:0'],
            'review_bonus_points' => ['nullable', 'integer', 'min:0'],
            'daily_login_base_points' => ['nullable', 'integer', 'min:0'],
            'daily_login_cap_days' => ['nullable', 'integer', 'min:1'],
            'points_redeem_value_bdt' => ['nullable', 'numeric', 'min:0'],
            'points_redeem_max_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Handle logo upload separately
        if ($request->hasFile('logo')) {
            $existing = Setting::where('key', 'logo')->first();
            if ($existing?->value && str_starts_with((string) $existing->value, '/storage/')) {
                Storage::disk('public')->delete(substr((string) $existing->value, strlen('/storage/')));
            }

            // Deliberately not through ImageOptimizer: this logo heads every
            // email, and Outlook on desktop can't show WebP.
            $path = $request->file('logo')->store('logos', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => '/storage/'.$path, 'group' => 'general']);
        }

        // Handled separately (not via the generic loop below): this request
        // is sent as multipart/form-data (because of the logo upload above),
        // which stringifies booleans — $request->input() would store the
        // literal string "1"/"0" instead of a real boolean.
        if ($request->has('guest_checkout_enabled')) {
            Setting::updateOrCreate(
                ['key' => 'guest_checkout_enabled'],
                ['value' => $request->boolean('guest_checkout_enabled'), 'group' => 'general'],
            );
        }

        $fields = [
            'site_name', 'contact_email', 'contact_phone', 'contact_address', 'currency_symbol',
            'legal_business_name', 'trade_licence_number',
            'order_notification_email', 'order_cancellation_email',
            'social_links',
            'whatsapp_number', 'messenger_link', 'bkash_number', 'nagad_number',
            'google_client_id', 'google_client_secret', 'google_redirect_uri',
            'facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri',
            'pathao_environment', 'pathao_client_id', 'pathao_client_secret',
            'pathao_username', 'pathao_password', 'pathao_store_id',
            'steadfast_api_key', 'steadfast_secret_key', 'steadfast_webhook_secret', 'steadfast_webhook_url',
            'shipping_rate_inside_dhaka', 'shipping_rate_outside_dhaka',
            'about_hero_eyebrow', 'about_hero_title', 'about_hero_title_highlight', 'about_hero_description',
            'about_stats', 'about_mission_eyebrow', 'about_mission_title', 'about_mission_body',
            'about_mission_features', 'about_values', 'about_team', 'about_cta_title', 'about_cta_description',
            'contact_page_hero_eyebrow', 'contact_page_hero_title', 'contact_page_hero_title_highlight',
            'contact_page_hero_description', 'contact_page_support_hours',
            'referral_enabled', 'referral_discount_type', 'referral_discount_value',
            'referral_min_order_amount', 'referral_reward_points', 'referral_reward_delay_days',
            'referral_unlock_order_count', 'referral_reward_cap_amount', 'referral_reward_cap_period',
            'loyalty_earn_rate_bdt_per_point', 'loyalty_earn_delay_days',
            'registration_bonus_points', 'review_bonus_points',
            'daily_login_base_points', 'daily_login_cap_days',
            'points_redeem_value_bdt', 'points_redeem_max_percent',
        ];

        foreach ($fields as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key), 'group' => $this->getGroup($key)]);
            }
        }

        // The shipping rate cards save one field each, same as Profile/Security/Connections
        // (see Inertia::flash('toast', ...) there) — an animated toast reads better for a
        // single-field card than the static "Saved." badge the other courier cards use.
        if ($request->has('shipping_rate_inside_dhaka')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Inside Dhaka delivery charge updated.']);
        } elseif ($request->has('shipping_rate_outside_dhaka')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Outside Dhaka delivery charge updated.']);
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Checks that the admin has `update` on every section whose fields are
     * actually present in this request — anything not listed in
     * FIELD_GROUPS belongs to the main `settings` section.
     */
    private function authorizeFieldGroups(Request $request): void
    {
        $admin = Auth::guard('admin')->user();
        $submitted = array_keys($request->all());
        $accountedFor = [];

        foreach (self::FIELD_GROUPS as $section => $fields) {
            $accountedFor = [...$accountedFor, ...$fields];

            if (array_intersect($fields, $submitted) !== [] && ! $admin->canAccess($section, 'update')) {
                abort(403, "You don't have permission to update {$section}.");
            }
        }

        if (array_diff($submitted, $accountedFor, ['_method']) !== [] && ! $admin->canAccess('settings', 'update')) {
            abort(403, "You don't have permission to update settings.");
        }
    }

    /**
     * List the merchant's Pathao stores so the admin can pick one instead of
     * typing a store_id by hand (per Pathao's "Get Merchant Store Info" API).
     * Requires Client ID/Secret and account email/password to already be saved.
     */
    public function pathaoStores(): JsonResponse
    {
        try {
            $stores = $this->pathao->getStores();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['stores' => $stores]);
    }

    /**
     * Confirm the configured Steadfast Api Key/Secret Key work by fetching
     * the merchant's current balance.
     */
    public function steadfastBalance(): JsonResponse
    {
        try {
            $balance = $this->steadfast->getBalance();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($balance);
    }

    /** @return Collection<string, mixed> */
    /**
     * @param  list<string>  $keys  only these are sent to the browser — see
     *                              the SEC-002 note on GENERAL_FIELDS above.
     */
    private function settingsProp(array $keys): Collection
    {
        return Setting::whereIn('key', $keys)->get()->keyBy('key')->map(fn ($s) => $s->value);
    }

    private function getGroup(string $key): string
    {
        return match (true) {
            in_array($key, ['site_name', 'logo', 'currency_symbol', 'guest_checkout_enabled']) => 'general',
            in_array($key, ['order_notification_email', 'order_cancellation_email']) => 'notifications',
            in_array($key, ['contact_email', 'contact_phone', 'contact_address', 'legal_business_name', 'trade_licence_number']) => 'contact',
            in_array($key, ['social_links']) => 'social',
            in_array($key, ['whatsapp_number', 'messenger_link']) => 'chat_widgets',
            in_array($key, ['bkash_number', 'nagad_number']) => 'payments',
            in_array($key, ['google_client_id', 'google_client_secret', 'google_redirect_uri', 'facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri']) => 'social_login',
            in_array($key, ['pathao_environment', 'pathao_client_id', 'pathao_client_secret', 'pathao_username', 'pathao_password', 'pathao_store_id']) => 'courier',
            in_array($key, ['steadfast_api_key', 'steadfast_secret_key', 'steadfast_webhook_secret', 'steadfast_webhook_url']) => 'courier',
            in_array($key, ['shipping_rate_inside_dhaka', 'shipping_rate_outside_dhaka']) => 'shipping',
            str_starts_with($key, 'about_') => 'about_page',
            str_starts_with($key, 'contact_page_') => 'contact_page',
            str_starts_with($key, 'referral_') => 'referral',
            str_starts_with($key, 'loyalty_') => 'referral',
            in_array($key, ['registration_bonus_points', 'review_bonus_points', 'daily_login_base_points', 'daily_login_cap_days', 'points_redeem_value_bdt', 'points_redeem_max_percent']) => 'referral',
            default => 'integrations',
        };
    }
}
