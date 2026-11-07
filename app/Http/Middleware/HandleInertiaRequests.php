<?php

namespace App\Http\Middleware;

use App\Models\Address;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wishlist;
use App\Services\BundleService;
use App\Support\CartManager;
use App\Support\LayoutCache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Routes that should not use SSR — these are authenticated or browser-heavy pages.
     *
     * This is matched against the request PATH via `$request->is(...)`
     * (Illuminate\Foundation\Http\Middleware\Concerns\ExcludesPaths), not
     * the Inertia component name — and `foo/*` alone never matches the
     * bare `/foo` path, only deeper segments, so a section's own index
     * page has to be listed separately from its `/*` wildcard. Most of
     * the entries below were previously missing that bare form (or, for
     * auth, assumed a `/auth/*` URL prefix that was never actually true —
     * Fortify's pages are top-level routes like `/login`, `/register`),
     * so despite the intent, almost none of these were actually excluded
     * from SSR before.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = [
        'admin',
        'admin/*',

        // Fortify's auth pages (top-level routes, no shared `/auth/*` prefix).
        'login',
        'register',
        'forgot-password',
        'reset-password/*',
        'email/verify',
        'email/verify/*',
        'user/confirm-password',

        'account',
        'account/*',
        'settings',
        'settings/*',

        // Both render totals straight from the client-only zustand cart
        // store (localStorage), which the server has no access to for a
        // guest (no server-side Cart row exists until they sync via
        // login) — SSR-ing them always disagrees with the client's real
        // numbers, causing a hydration-mismatch React recovers from by
        // discarding and rebuilding the subtree, which can disrupt
        // effects that should have run cleanly on mount (e.g.
        // abandoned-checkout tracking).
        'checkout',
        'checkout/*',
        'cart',
        'cart/*',

        // A guest's saved-item count only exists in localStorage.
        'wishlist',
    ];

    public function __construct(
        private readonly CartManager $cartManager,
        private readonly BundleService $bundles,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $this->flashSessionMessageAsToast($request);

        // Resolved eagerly (not lazily, unlike the per-user props below) so
        // each can be content-hashed into its once-prop key — see onceKey().
        // All three remain pure LayoutCache reads, same as before; this adds
        // no new query, only a cheap in-memory hash of the already-resolved
        // value.
        $navCategories = LayoutCache::navCategories();
        $homepageSettings = LayoutCache::homepageSettings();
        $announcementBar = ['announcement_bar' => $homepageSettings['announcement_bar'] ?? null];
        $settingsData = $this->resolveSettings($homepageSettings);
        $popularKeywords = LayoutCache::popularKeywords();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'admin' => $request->user('admin'),
            ],
            'cart' => fn () => $this->cartManager->serializeForRequest($request),
            'active_bundle_recipes' => fn () => $this->bundles->activeRecipes(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Content-hash once-props: sent in full on the first visit (and
            // on every direct/non-Inertia load — see excludeFromInitialResponse()
            // in inertia-laravel's PropsResolver, gated on $isInertia), then
            // omitted on later SPA navigations as long as the resolved value's
            // hash is unchanged. The moment an admin edit changes the actual
            // value (same LayoutCache this already read before today), the
            // hash changes, so the next navigation sends it fresh again —
            // exactly as today, not a TTL-bounded approximation of it. See
            // onceKey() for why this is exact rather than time-based.
            'navCategories' => Inertia::once(fn () => $navCategories)->as($this->onceKey('navCategories', $navCategories)),
            'settings' => Inertia::once(fn () => $settingsData)->as($this->onceKey('settings', $settingsData)),
            'popular_keywords' => Inertia::once(fn () => $popularKeywords)->as($this->onceKey('popular_keywords', $popularKeywords)),
            // No session exists on error pages for unmatched URLs (rendered before the web middleware runs).
            'flash' => fn () => $request->hasSession() ? [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'newToken' => $request->session()->get('newToken'),
            ] : [
                'success' => null,
                'error' => null,
                'info' => null,
                'newToken' => null,
            ],
            // Only the slice Header.tsx actually reads (homepage_settings.announcement_bar)
            // — the rest of this section-settings blob is Home-only and now
            // comes from HomeController's own render props instead, which
            // (page props override shared props of the same key) replaces
            // this slimmer value with the full one specifically on the Home
            // response. Every other page keeps getting just this slice, and
            // keeps getting it exactly as a once-prop like the three above.
            'homepage_settings' => Inertia::once(fn () => $announcementBar)->as($this->onceKey('homepage_settings', $announcementBar)),
            'wishlist_ids' => fn () => $request->user()
                ? Wishlist::where('user_id', $request->user()->id)->pluck('product_id')->toArray()
                : [],
            'account_stats' => fn () => $request->user() ? [
                'orders_count' => Order::withTrashed()->where('user_id', $request->user()->id)->count(),
                'wishlist_count' => Wishlist::where('user_id', $request->user()->id)->count(),
                'addresses_count' => Address::where('user_id', $request->user()->id)->count(),
            ] : null,
            'cart_base_shipping_rate' => fn () => $this->resolveBaseShippingRate($request),
            'unseen_orders_count' => fn () => $request->user('admin')
                ? Order::where('is_seen', false)->count()
                : null,
        ];
    }

    /**
     * A once-prop's resend trigger isn't time — it's this key changing.
     * Hashing the value LayoutCache already returned (never the cache store
     * itself) means the key changes if, and only if, the data an admin
     * actually controls changes — identical in effect to today's "every
     * navigation re-reads LayoutCache" behavior, just without the bytes on
     * the navigations where nothing changed. No TTL, no separate
     * invalidation bookkeeping: this can't drift out of sync with
     * LayoutCache because it's never anything other than a function of the
     * exact value LayoutCache just returned.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function onceKey(string $prop, array $value): string
    {
        return $prop.':'.md5(serialize($value));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(array $homepageSettings): array
    {
        $db = collect(LayoutCache::settings());
        $socialLinks = $db->get('social_links', []);

        return [
            'site_name' => $db->get('site_name', config('app.name', 'ShoppyDrive')),
            'logo' => $db->get('logo'),
            'currency_symbol' => $db->get('currency_symbol', '৳'),
            'contact_email' => $db->get('contact_email', 'support@shoppydrive.com'),
            'contact_phone' => $db->get('contact_phone', '+880 1XXX XXXXXX'),
            'contact_address' => $db->get('contact_address'),
            // BIZ-034: seller identity, shown in the footer when set.
            'legal_business_name' => $db->get('legal_business_name'),
            'trade_licence_number' => $db->get('trade_licence_number'),
            'support_hours' => $db->get('contact_page_support_hours') ?: 'Every day, 9 AM–9 PM',
            'social_links' => [
                'facebook' => $socialLinks['facebook'] ?? null,
                'instagram' => $socialLinks['instagram'] ?? null,
                'twitter' => $socialLinks['twitter'] ?? null,
                'youtube' => $socialLinks['youtube'] ?? null,
                'tiktok' => $socialLinks['tiktok'] ?? null,
            ],
            'google_analytics_id' => $db->get('google_analytics_id'),
            'meta_pixel_id' => $db->get('meta_pixel_id'),
            'whatsapp_number' => $db->get('whatsapp_number'),
            'messenger_link' => $db->get('messenger_link'),
            'ai_assistant_enabled' => (bool) $db->get('ai_assistant_enabled', false),
            'google_login_enabled' => filled(config('services.google.client_id')),
            'facebook_login_enabled' => filled(config('services.facebook.client_id')),
            'referral_enabled' => (bool) $db->get('referral_enabled', false),
            'referral_discount_type' => $db->get('referral_discount_type', 'percent'),
            'referral_discount_value' => (float) $db->get('referral_discount_value', 0),
            'free_delivery_threshold' => (float) ($homepageSettings['announcement_bar']['metadata']['free_delivery_amount'] ?? 0),
            'shipping_rate_inside_dhaka' => Setting::shippingRateFor('Dhaka', 'Inside Dhaka City Corporation'),
            'shipping_rate_outside_dhaka' => Setting::shippingRateFor(null, null),
        ];
    }

    /**
     * Admin controllers report outcomes with `->with('success'|'error'|'info', ...)`.
     * Surface those as the standard toast (see `useFlashToast`) instead of
     * per-page banners. This runs before the controller, and Inertia pulls
     * flash data when the page renders, so the toast lands in this same
     * response. Skipped when the controller already flashed a toast itself.
     */
    private function flashSessionMessageAsToast(Request $request): void
    {
        if (! $request->hasSession() || ! $request->is('admin', 'admin/*')) {
            return;
        }

        if (array_key_exists('toast', Inertia::getFlashed($request))) {
            return;
        }

        foreach (['error', 'success', 'info'] as $type) {
            $message = $request->session()->get($type);

            if (is_string($message) && $message !== '') {
                Inertia::flash('toast', ['type' => $type, 'message' => $message]);

                return;
            }
        }
    }

    private function resolveBaseShippingRate(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $default = Address::where('user_id', $user->id)
            ->where('is_default', true)
            ->first(['state', 'city']);

        if (! $default) {
            return null;
        }

        return (int) Setting::shippingRateFor($default->state, $default->city);
    }
}
