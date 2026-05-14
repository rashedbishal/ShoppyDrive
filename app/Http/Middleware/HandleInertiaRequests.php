<?php

namespace App\Http\Middleware;

use App\Models\Address;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\PopularKeyword;
use App\Models\PromotionalBanner;
use App\Models\Setting;
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
            'settings' => function () {
                $db = Setting::all()->keyBy('key')->map(fn ($s) => $s->value);
                $socialLinks = $db->get('social_links', []);

                return [
                    'site_name' => $db->get('site_name', config('app.name', 'ShoppyDrive')),
                    'logo' => $db->get('logo'),
                    'currency_symbol' => $db->get('currency_symbol', '৳'),
                    'contact_email' => $db->get('contact_email', 'support@shoppydrive.com'),
                    'contact_phone' => $db->get('contact_phone', '+880 1XXX XXXXXX'),
                    'social_links' => [
                        'facebook' => $socialLinks['facebook'] ?? null,
                        'instagram' => $socialLinks['instagram'] ?? null,
                        'twitter' => $socialLinks['twitter'] ?? null,
                    ],
                    'google_analytics_id' => $db->get('google_analytics_id'),
                    'meta_pixel_id' => $db->get('meta_pixel_id'),
                    'whatsapp_number' => $db->get('whatsapp_number'),
                    'messenger_link' => $db->get('messenger_link'),
                    'free_delivery_threshold' => (function () {
                        $bar = HomepageSetting::where('section_key', 'announcement_bar')->first();

                        return (float) ($bar?->metadata['free_delivery_amount'] ?? 0);
                    })(),
                ];
            },
            'popular_keywords' => fn () => PopularKeyword::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('keyword')
                ->toArray(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'newToken' => $request->session()->get('newToken'),
            ],
            'homepage_settings' => fn () => HomepageSetting::orderBy('sort_order')->get()->keyBy('section_key'),
            'hero_slides' => fn () => HeroSlide::where('is_active', true)->orderBy('sort_order')->get(),
            'promotional_banners' => fn () => PromotionalBanner::where('is_active', true)->orderBy('sort_order')->get(),
            'wishlist_ids' => fn () => $request->user()
                ? Wishlist::where('user_id', $request->user()->id)->pluck('product_id')->toArray()
                : [],
            'account_stats' => fn () => $request->user() ? [
                'orders_count' => Order::where('user_id', $request->user()->id)->count(),
                'wishlist_count' => Wishlist::where('user_id', $request->user()->id)->count(),
                'addresses_count' => Address::where('user_id', $request->user()->id)->count(),
            ] : null,
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

        // Check free delivery threshold
        $bar = HomepageSetting::where('section_key', 'announcement_bar')->first();
        $threshold = (float) ($bar?->metadata['free_delivery_amount'] ?? 0);

        if ($threshold > 0) {
            $cart = $this->cartManager->getCurrentCart($request);
            if ($cart) {
                $subtotal = $cart->items->sum(fn ($item) => (float) $item->price * $item->quantity);
                if ($subtotal >= $threshold) {
                    return 0;
                }

                // Per-product free delivery check
                $cart->load('items.product');
                if ($cart->items->isNotEmpty()) {
                    $allFree = $cart->items->every(function ($item) {
                        $product = $item->product;
                        if (! $product->free_delivery) {
                            return false;
                        }
                        if ($product->free_delivery_min_qty !== null) {
                            return $item->quantity >= $product->free_delivery_min_qty;
                        }

                        return true;
                    });

                    if ($allFree) {
                        return 0;
                    }
                }
            }
        }

        return $default->state === 'Dhaka' && $default->city === 'Inside Dhaka City Corporation'
            ? 60
            : 120;
    }
}
