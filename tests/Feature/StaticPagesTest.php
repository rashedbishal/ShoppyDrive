<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the privacy policy page with Google and Facebook sign-in disclosures', function (): void {
    Page::create([
        'title' => 'Privacy Policy',
        'slug' => 'privacy-policy',
        'content' => '<h2>Signing In With Google or Facebook</h2><p>Google API Services User Data Policy</p><h2>Data Deletion</h2><p>Delete Account</p>',
        'is_published' => true,
    ]);

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
    Page::create([
        'title' => 'Terms of Service',
        'slug' => 'terms',
        'content' => '<h2>Signing In With Google or Facebook</h2><p>Sign in with Google</p>',
        'is_published' => true,
    ]);

    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('static/Page')
            ->where('page.slug', 'terms')
            ->where('page.is_published', true)
        );
});

it('returns 404 for an unpublished privacy policy page', function (): void {
    Page::create([
        'title' => 'Privacy Policy',
        'slug' => 'privacy-policy',
        'content' => '<h2>Draft</h2>',
        'is_published' => false,
    ]);

    $this->get(route('privacy-policy'))->assertNotFound();
});
