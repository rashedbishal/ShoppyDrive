<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Unable to login with '.ucfirst($provider).'.']);
        }

        if (Auth::check()) {
            return $this->connectToCurrentUser($provider, $socialUser);
        }

        if (! $socialUser->getEmail()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your '.ucfirst($provider).' account has no email address attached, so we can\'t use it to sign in. Please try a different method.',
            ]);
        }

        // PROMO-LOY-001 follow-up: this closure calls
        // `LoyaltyPointsService::grantRegistrationBonus()`, which runs its
        // own `DB::transaction($callback, 3)`. Since that call executes
        // nested inside this one, Laravel's own transaction manager
        // (`Connection::handleTransactionException()`) recognizes the
        // nesting and refuses to retry at that inner level on a real
        // concurrency error — it decrements the transaction counter and
        // immediately rethrows instead, because a deadlock/lock-wait
        // rolls back the *entire* underlying transaction, not just the
        // innermost savepoint, so retrying only the inner closure could
        // otherwise resume on a connection the database had already fully
        // rolled back out from under. That rethrow lands here, so the
        // retry has to happen at this, the outermost and only truly
        // retryable, transaction boundary. The whole closure is safe to
        // re-run from scratch: every read inside it (`SocialAccount`/
        // `User` lookups) is naturally repeated after a full rollback and
        // nothing outside the transaction is touched (no mail/queue/HTTP
        // side effects), so a retried attempt can't double up or desync
        // anything it created.
        $user = DB::transaction(function () use ($socialUser, $provider) {
            $account = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($account) {
                if ($account->user) {
                    return $account->user;
                }

                // Stale link left behind by a deleted account — drop it and
                // fall through to a normal lookup/create below.
                $account->delete();
            }

            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user && $user->anonymized_at !== null) {
                return null;
            }

            if (! $user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::password(24)),
                    'has_password' => false,
                    'referred_by_id' => $this->referrals->resolveReferrerId(session('pending_referral_code')),
                ]);

                $this->loyalty->grantRegistrationBonus($user);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            return $user;
        }, 3);

        session()->forget('pending_referral_code');

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active.']);
        }

        Auth::login($user);

        return redirect(config('fortify.home'));
    }

    /**
     * Attach a social provider to the currently authenticated user, triggered
     * from the "Connected accounts" settings page rather than the login page.
     */
    private function connectToCurrentUser(string $provider, SocialiteUser $socialUser): RedirectResponse
    {
        $user = Auth::user();

        if ($user instanceof User && $user->anonymized_at !== null) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active.']);
        }

        $existing = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existing && ! $existing->user) {
            // Stale link left behind by a deleted account — drop it so it
            // can be freely reconnected below.
            $existing->delete();
            $existing = null;
        }

        if ($existing && $existing->user_id !== $user->id) {
            return redirect()->route('connections.edit')->withErrors([
                'connections' => 'This '.ucfirst($provider).' account is already linked to another ShoppyDrive account.',
            ]);
        }

        if (! $existing) {
            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($provider).' account connected.']);

        return redirect()->route('connections.edit');
    }
}
