<?php

namespace App\Http\Middleware;

use App\Models\Address;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\PromotionalBanner;
use App\Models\Wishlist;
use App\Support\CartManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Routes that should not use SSR — these are authenticated or browser-heavy pages.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = [
        'admin/*',
        'auth/*',
        'account/*',
        'settings/*',
    ];

    public function __construct(private readonly CartManager $cartManager) {}

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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'admin' => $request->user('admin'),
            ],
            'cart' => fn () => $this->cartManager->serializeForRequest($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'navCategories' => fn () => Category::active()
                ->with(['subCategories' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')->select(['id', 'name', 'slug', 'category_id', 'description'])])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description']),
            'settings' => [
                'site_name' => config('app.name', 'ShoppyDrive'),
                'logo' => null,
                'currency_symbol' => '৳',
                'contact_email' => 'support@shoppydrive.com',
                'contact_phone' => '+880 1XXX XXXXXX',
                'social_links' => [
                    'facebook' => 'https://facebook.com',
                    'instagram' => 'https://instagram.com',
                    'twitter' => 'https://twitter.com',
                ],
                'google_analytics_id' => null,
                'meta_pixel_id' => null,
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'homepage_settings' => fn () => HomepageSetting::orderBy('sort_order')->get()->keyBy('section_key'),
            'hero_slides' => fn () => HeroSlide::where('is_active', true)->orderBy('sort_order')->get(),
            'promotional_banners' => fn () => PromotionalBanner::where('is_active', true)->orderBy('sort_order')->get(),
            'wishlist_ids' => fn () => $request->user()
                ? Wishlist::where('user_id', $request->user()->id)->pluck('product_id')->toArray()
                : [],
            'cart_shipping_cost' => fn () => $this->resolveCartShippingCost($request),
        ];
    }

    private function resolveCartShippingCost(Request $request): ?int
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

        return $default->state === 'Dhaka' && $default->city === 'Inside Dhaka City Corporation'
            ? 60
            : 120;
    }
}
