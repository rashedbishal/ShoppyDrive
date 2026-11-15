<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-52: these settings pages send their own `settings` prop (their form
 * values, no logo), which replaces the shared site settings. The CMS layout
 * showed the generic mark there instead of the uploaded logo.
 */

test('DISC-52: the uploaded logo still reaches the CMS layout on settings pages that send their own settings', function (string $routeName) {
    Setting::create(['key' => 'logo', 'value' => '/storage/settings/logo.png', 'group' => 'general']);
    actingAs(createMotherAdmin(), 'admin');

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('settings.logo')
            ->where('brand.logo', '/storage/settings/logo.png'));
})->with([
    'about page' => 'admin.settings.about-page',
    'contact page' => 'admin.settings.contact-page',
    'social config' => 'admin.settings.social',
    'courier config' => 'admin.settings.courier',
    'referral program' => 'admin.settings.referral',
    'marketing and tracking' => 'admin.settings.marketing.edit',
]);

test('DISC-52: the CMS layout reads the logo from brand first, larger, with a shimmer and a larger Admin Panel label', function () {
    // Source-level check (this project has no JavaScript test runner).
    $layout = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/layouts/admin-layout.tsx')));
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($layout)->toContain('page.props.brand?.logo ?? (page.props.settings as AppSettings)?.logo ?? null')
        ->toContain("className={collapsed ? 'h-auto w-14' : 'h-11'}")
        ->toContain('<p className="text-sm font-semibold whitespace-nowrap text-slate-700"> Admin Panel </p>')
        ->toContain('className="admin-logo-shimmer"')
        ->and($css)->toContain('animation: admin-logo-shimmer 2.8s ease-in-out infinite;');
});
