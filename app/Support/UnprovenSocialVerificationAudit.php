<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FSA-09 legacy-account audit. Before this finding's fix, a Facebook login
 * set `email_verified_at` unconditionally for a brand-new account, and
 * linked to any existing account matched purely by email with no gate at
 * all (Facebook has no email-verification claim to check, unlike Google).
 * The fix (SocialAuthController::providerVerifiedEmail()) closed both
 * paths going forward, but cannot retroactively tell a legitimate
 * pre-existing Facebook-linked account apart from one created or taken
 * over through that gap — nothing in the data distinguishes "verified via
 * real OTP" from "verified because the old code trusted Facebook blindly".
 *
 * This identifies the exact population whose current state could only
 * have arisen from that old, now-closed path: `email_verified_at` is set
 * (claims verified), `has_password` is false (never completed the normal
 * password + OTP registration — their only verification evidence is a
 * provider that doesn't actually prove email ownership), and they have a
 * Facebook-provider social account. A new account created through today's
 * fixed code can never match this (Facebook-only signups are now left
 * unverified until OTP), so every row this finds predates the fix.
 *
 * Deliberately read-only: no account is modified, locked out, merged or
 * re-verified here. What to do with the accounts this finds — notify
 * them, force re-verification, or leave them — is the explicit owner
 * decision this audit exists to inform, not one this class makes.
 */
class UnprovenSocialVerificationAudit
{
    private static function baseQuery(): Builder
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where('has_password', false)
            ->whereHas('socialAccounts', fn ($q) => $q->where('provider', 'facebook'))
            ->whereNull('anonymized_at');
    }

    /**
     * @return Collection<int, User>
     */
    public static function find(): Collection
    {
        return self::baseQuery()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'email_verified_at', 'created_at']);
    }

    /**
     * Aggregate-only counts, for a context where even the matching rows'
     * name/email must never be selected at all (not just withheld from
     * output) — e.g. a production summary run. Never touches `name` or
     * `email`: the query itself only ever selects `id`/`created_at`.
     *
     * @return array{total: int, earliest_created_at: string|null, latest_created_at: string|null, by_month: array<string, int>}
     */
    public static function summarize(): array
    {
        $rows = self::baseQuery()->get(['id', 'created_at']);

        if ($rows->isEmpty()) {
            return ['total' => 0, 'earliest_created_at' => null, 'latest_created_at' => null, 'by_month' => []];
        }

        $byMonth = $rows
            ->groupBy(fn ($user) => $user->created_at?->format('Y-m') ?? 'unknown')
            ->map(fn ($group) => $group->count())
            ->sortKeys()
            ->all();

        return [
            'total' => $rows->count(),
            'earliest_created_at' => $rows->min('created_at')?->toDateTimeString(),
            'latest_created_at' => $rows->max('created_at')?->toDateTimeString(),
            'by_month' => $byMonth,
        ];
    }
}
