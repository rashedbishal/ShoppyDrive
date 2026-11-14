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
    /**
     * DISC-33 (owner decision 2026-11-14): customers need 8+ characters in
     * production, admins keep 12+. The other production rules (upper and lower
     * case, a number, a symbol, the breach check) apply to both.
     */
    private const int CUSTOMER_MIN_LENGTH = 8;

    private const int ADMIN_MIN_LENGTH = 12;

    /**
     * Outside production Password::default() is Laravel's Password::min(8).
     */
    private const int NON_PRODUCTION_MIN_LENGTH = 8;

    /**
     * The customer production rule, registered as Password::defaults() by
     * AppServiceProvider::configureDefaults(), so every customer form that
     * uses Password::default() gets it.
     */
    public static function rule(): Password
    {
        return self::productionRule(self::CUSTOMER_MIN_LENGTH);
    }

    /**
     * DISC-33: the admin rule, used explicitly by admin create/edit and
     * `admin:create-mother` instead of the customer default. Outside
     * production it falls back exactly like Password::default().
     */
    public static function adminRule(): Password
    {
        if (! app()->isProduction()) {
            return Password::min(self::NON_PRODUCTION_MIN_LENGTH);
        }

        return self::productionRule(self::ADMIN_MIN_LENGTH);
    }

    /**
     * DISC-32: the customer policy in machine-readable form, for the Change
     * Password page's strength meter and live checklist. Branches on the
     * environment exactly like requirements(). `uncompromised` (the breach
     * check) can only run on the server, so the page shows it as "checked
     * when you save".
     *
     * @return array{min_length: int, mixed_case: bool, numbers: bool, symbols: bool, uncompromised: bool}
     */
    public static function checks(): array
    {
        if (! app()->isProduction()) {
            return [
                'min_length' => self::NON_PRODUCTION_MIN_LENGTH,
                'mixed_case' => false,
                'numbers' => false,
                'symbols' => false,
                'uncompromised' => false,
            ];
        }

        return [
            'min_length' => self::CUSTOMER_MIN_LENGTH,
            'mixed_case' => true,
            'numbers' => true,
            'symbols' => true,
            'uncompromised' => true,
        ];
    }

    /**
     * The customer rule in plain English (shared as `password_requirements`).
     * Outside production AppServiceProvider::configureDefaults() never calls
     * rule() at all, so this branches the same way to stay accurate there too.
     *
     * @return list<string>
     */
    public static function requirements(): array
    {
        return self::describe(self::CUSTOMER_MIN_LENGTH);
    }

    /**
     * DISC-33: adminRule() in plain English, sent by the admin create/edit
     * pages in place of the customer list.
     *
     * @return list<string>
     */
    public static function adminRequirements(): array
    {
        return self::describe(self::ADMIN_MIN_LENGTH);
    }

    private static function productionRule(int $minLength): Password
    {
        return Password::min($minLength)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * @return list<string>
     */
    private static function describe(int $productionMinLength): array
    {
        if (! app()->isProduction()) {
            return ['At least '.self::NON_PRODUCTION_MIN_LENGTH.' characters'];
        }

        return [
            'At least '.$productionMinLength.' characters',
            'Upper and lower case letters',
            'At least one number',
            'At least one symbol (e.g. ! @ # $ %)',
            "Not a password that's appeared in a known data breach",
        ];
    }
}
