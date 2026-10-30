<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * DB-001: re-verification of the mother admin's password (both steps of
 * permanent order deletion, clearing the audit trail). Brute-force limit:
 * MAX_FAILURES wrong passwords per DECAY_SECONDS per admin, across all of
 * those actions; while locked, even the right password is refused. Correct
 * passwords don't count, so legitimate work isn't throttled — a separate,
 * generous request cap (`mother-actions` limiter) bounds request volume.
 */
final class MotherPasswordGate
{
    public const MAX_FAILURES = 5;

    public const DECAY_SECONDS = 900;

    public static function isLocked(Admin $admin): bool
    {
        return RateLimiter::tooManyAttempts(self::key($admin), self::MAX_FAILURES);
    }

    public static function lockedMessage(Admin $admin): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn(self::key($admin)) / 60));

        return "Too many incorrect password attempts. Try again in {$minutes} minute(s).";
    }

    /**
     * Callers must check isLocked() first. A wrong (or empty) password counts.
     */
    public static function verify(Admin $admin, string $password): bool
    {
        if ($password !== '' && Hash::check($password, $admin->password)) {
            return true;
        }

        RateLimiter::hit(self::key($admin), self::DECAY_SECONDS);

        return false;
    }

    private static function key(Admin $admin): string
    {
        return 'mother-password-failures:'.$admin->id;
    }
}
