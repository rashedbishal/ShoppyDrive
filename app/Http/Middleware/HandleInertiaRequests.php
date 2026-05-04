<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
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
        ];
    }
}
