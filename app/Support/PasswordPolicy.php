<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for the production password policy: both the
 * validation rule itself (AppServiceProvider::configureDefaults(), via
 * Password::defaults()) and the plain-English list shown on every page
 * that sets a new password, so the two can never silently drift apart.
 * A tester reported the registration page's password hint ("8+
 * characters") was actively wrong — the real production rule is far
 * stricter — and a customer only ever learned what was still missing one
 * rule at a time, across repeated failed submissions.
 */
class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * Mirrors rule() in plain English. Outside production,
     * AppServiceProvider::configureDefaults() never calls rule() at all —
     * Password::default() falls back to Laravel's own Password::min(8) —
     * so this branches the same way to stay accurate there too.
     *
     * @return list<string>
     */
    public static function requirements(): array
    {
        if (! app()->isProduction()) {
            return ['At least 8 characters'];
        }

        return [
            'At least 12 characters',
            'Upper and lower case letters',
            'At least one number',
            'At least one symbol (e.g. ! @ # $ %)',
            "Not a password that's appeared in a known data breach",
        ];
    }
}
