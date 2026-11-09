<?php

declare(strict_types=1);

/*
 * A tester reported the registration page's password hint ("8+ characters")
 * was actively wrong in production, where the real policy is far stricter
 * (App\Support\PasswordPolicy / AppServiceProvider::configureDefaults()) —
 * a customer only learned what was still missing one rule at a time, across
 * repeated failed submits. The server now shares the real requirement list
 * as `password_requirements`, consumed by every page that sets a new
 * password (register, reset-password, settings/security, settings/connections).
 */

use App\Support\PasswordPolicy;
use Inertia\Testing\AssertableInertia as Assert;

test('requirements() matches the non-production fallback outside production', function () {
    expect(app()->isProduction())->toBeFalse();
    expect(PasswordPolicy::requirements())->toBe(['At least 8 characters']);
});

test('requirements() describes the full strengthened policy in production', function () {
    app()->detectEnvironment(fn () => 'production');
    expect(app()->isProduction())->toBeTrue();

    expect(PasswordPolicy::requirements())->toBe([
        'At least 12 characters',
        'Upper and lower case letters',
        'At least one number',
        'At least one symbol (e.g. ! @ # $ %)',
        "Not a password that's appeared in a known data breach",
    ]);
});

test('the registration page shares the real password requirements, not a hardcoded hint', function () {
    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('password_requirements', PasswordPolicy::requirements()));
});

test('the reset-password page shares the real password requirements', function () {
    $this->get(route('password.reset', ['token' => 'anything']).'?email=rahim@example.com')
        ->assertInertia(fn (Assert $page) => $page
            ->where('password_requirements', PasswordPolicy::requirements()));
});
