<?php

declare(strict_types=1);

use App\Models\Page;
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
        ->assertSee('Data Deletion', false);
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
