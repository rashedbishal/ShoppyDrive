<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the privacy policy page with Google and Facebook sign-in disclosures', function (): void {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/Page')
            ->where('page.slug', 'privacy-policy')
            ->where('page.is_published', true)
        )
        ->assertSee('Signing In With Google or Facebook', false)
        ->assertSee('Deleting Your Account', false);
});

it('renders the terms of service page', function (): void {
    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/Page')
            ->where('page.slug', 'terms')
            ->where('page.is_published', true)
        );
});

it('returns 404 for an unpublished privacy policy page', function (): void {
    Page::where('slug', 'privacy-policy')->update(['is_published' => false]);

    $this->get(route('privacy-policy'))->assertNotFound();
});

it('renders the about page with default content when nothing has been configured', function (): void {
    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/About')
            ->where('content.hero_title', "Bangladesh's Smartest")
            ->has('content.stats', 4)
            ->has('content.values', 4)
            ->has('content.team', 4)
        );
});

it('renders the contact page with default content when nothing has been configured', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/Contact')
            ->where('content.email', 'support@shoppydrive.com')
            ->where('content.phone', '+880 1700-000000')
        );
});

it('renders the contact page with admin-configured contact details', function (): void {
    Setting::create(['key' => 'contact_email', 'value' => 'help@shoppydrive.com', 'group' => 'contact']);
    Setting::create(['key' => 'contact_page_hero_title', 'value' => 'Need help?', 'group' => 'contact_page']);

    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/Contact')
            ->where('content.email', 'help@shoppydrive.com')
            ->where('content.hero_title', 'Need help?')
            // Untouched fields keep falling back to the defaults.
            ->where('content.phone', '+880 1700-000000')
        );
});

it('renders the about page with admin-configured content', function (): void {
    Setting::create(['key' => 'about_hero_title', 'value' => 'Our Custom Story', 'group' => 'about_page']);
    Setting::create(['key' => 'about_stats', 'value' => [
        ['value' => '10', 'label' => 'Custom Stat'],
    ], 'group' => 'about_page']);

    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/About')
            ->where('content.hero_title', 'Our Custom Story')
            ->where('content.stats', [
                ['value' => '10', 'label' => 'Custom Stat'],
            ])
            // Fields the admin hasn't touched keep falling back to the defaults.
            ->where('content.cta_title', 'Ready to start shopping?')
        );
});
