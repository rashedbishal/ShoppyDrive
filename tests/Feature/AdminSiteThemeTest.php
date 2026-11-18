<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * DISC-67: the storefront theme a visitor picks (localStorage `site_theme`)
 * was applied to admin pages too, and Neomorphism made the CMS form inputs
 * invisible (card and border the same colour). Admin pages never get it.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

const SITE_THEME_SCRIPT = "localStorage.getItem('site_theme')";

test('storefront pages apply the saved theme before paint', function () {
    expect($this->get(route('home'))->assertOk()->getContent())
        ->toContain(SITE_THEME_SCRIPT);
});

test('the admin login page does not apply the storefront theme', function () {
    expect($this->get(route('admin.login'))->assertOk()->getContent())
        ->not->toContain(SITE_THEME_SCRIPT);
});

test('admin pages do not apply the storefront theme', function () {
    expect($this->actingAs(createAdmin(), 'admin')->get(route('admin.dashboard'))->assertOk()->getContent())
        ->not->toContain(SITE_THEME_SCRIPT);
});
