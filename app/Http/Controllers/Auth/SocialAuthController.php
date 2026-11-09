<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use App\Support\PostLoginRedirect;
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
        //
        // Returns a {status, user} pair rather than a bare User|null: two
        // independent failure modes can end this closure early (an
        // anonymized account, or Google's own unverified-email claim on
        // SEC-01 below) and each needs its own distinct, correct error
        // message outside the transaction — collapsing both to `null`
        // would make them indistinguishable by the time control returns
        // here.
        $result = DB::transaction(function () use ($socialUser, $provider): array {
            $account = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($account) {
                if ($account->user) {
                    return ['status' => 'ok', 'user' => $account->user];
                }

                // Stale link left behind by a deleted account — drop it and
                // fall through to a normal lookup/create below.
                $account->delete();
            }

            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                if ($user->anonymized_at !== null) {
                    return ['status' => 'anonymized', 'user' => null];
                }

                // FSA-09: an existing account matched purely by email is
                // linked only when the provider itself vouches that this
                // person owns the address (see providerVerifiedEmail()).
                // Facebook never does, so it never links by email.
                if (! $this->providerVerifiedEmail($provider, $socialUser)) {
                    return ['status' => $provider === 'google' ? 'unverified_email' : 'link_requires_login', 'user' => null];
                }

                // The provider proved ownership but nobody ever proved it
                // locally, so whoever registered this account (possibly
                // someone else, pre-registering the address) must not keep
                // any way back in.
                if (! $user->hasVerifiedEmail()) {
                    $user = $this->claimUnverifiedAccount($user);
                    // BIZ-024: the claim proves the email (idempotent).
                    $this->loyalty->grantRegistrationBonus($user);
                }
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    // FSA-09: only a provider-verified address counts as
                    // verified here; otherwise the normal OTP flow proves it.
                    'email_verified_at' => $this->providerVerifiedEmail($provider, $socialUser) ? now() : null,
                    'password' => Hash::make(Str::password(24)),
                    'has_password' => false,
                    'referred_by_id' => $this->referrals->resolveReferrerId(session('pending_referral_code')),
                ]);

                // BIZ-024: only a provider-verified email earns the sign-up
                // bonus now; others get it when they verify by code.
                if ($user->hasVerifiedEmail()) {
                    $this->loyalty->grantRegistrationBonus($user);
                }
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            return ['status' => 'ok', 'user' => $user];
        }, 3);

        session()->forget('pending_referral_code');

        if ($result['status'] === 'anonymized') {
            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active.']);
        }

        if ($result['status'] === 'unverified_email') {
            return redirect()->route('login')->withErrors([
                'email' => 'An account with this email already exists. Please log in with your password or an already-connected provider, then connect Google from your account settings.',
            ]);
        }

        if ($result['status'] === 'link_requires_login') {
            return redirect()->route('login')->withErrors([
                'email' => 'An account with this email already exists. Please log in with your password or Google, then connect '.ucfirst($provider).' from your account settings.',
            ]);
        }

        Auth::login($result['user']);

        // FSA-09 policy update (2026-11-03, explicit owner decision):
        // successful social authentication is itself the login method —
        // it must never be followed by a mandatory email-OTP challenge,
        // for a brand-new unverified account, a claimed account, or an
        // existing linked identity that's still unverified. This applies
        // uniformly to every path that reaches this line, old or new.
        //
        // This does not silently grant verified-only access: nothing here
        // sets `email_verified_at`, and every route that actually requires
        // it (`account.php`/`settings.php`'s `verified` middleware,
        // `EnsureCheckoutAccess`) is completely unchanged and still
        // redirects an unverified user to `verification.notice` the
        // moment they reach one — exactly the same lazy gate a manual,
        // password-based unverified registration already goes through.
        // Only the *proactive* redirect immediately after a successful
        // social login is removed; manual registration's own immediate
        // OTP requirement (`RegisterResponse`) is untouched by this file.
        return PostLoginRedirect::redirect(config('fortify.home'));
    }

    /**
     * FSA-09: whether the provider itself asserts the email is verified.
     * Google's OpenID userinfo carries an `email_verified` claim (raw
     * payload only — no SocialiteUser method exposes it); anything but an
     * explicit `true` is not proof. Facebook's integration has no email
     * verification claim (its requested `verified` field is a deprecated
     * account-level flag, not about the email), so it never vouches.
     */
    private function providerVerifiedEmail(string $provider, SocialiteUser $socialUser): bool
    {
        return $provider === 'google'
            && ($socialUser->getRaw()['email_verified'] ?? null) === true;
    }

    /**
     * FSA-09: hands an unverified local account to the person a provider
     * has just proven owns its email. Anyone who registered it before them
     * loses every way back in: the password is replaced (and with it, via
     * the `auth.session` middleware, every other session), the remember
     * token is cycled, and social links attached while it was unverified
     * are removed. A referrer attached at registration is dropped too when
     * the account has never ordered, so a pre-registered referral can't
     * earn from the real owner's purchases. Must run inside the caller's
     * transaction.
     */
    private function claimUnverifiedAccount(User $user): User
    {
        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

        $locked->socialAccounts()->delete();

        $updates = [
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(24)),
            'has_password' => false,
            'remember_token' => Str::random(60),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ];

        if (! Order::withTrashed()->where('user_id', $locked->id)->exists()) {
            $updates['referred_by_id'] = null;
        }

        $locked->forceFill($updates)->save();

        return $locked;
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

        // FSA-09: an unverified account may belong to someone who only
        // pre-registered the address; a provider attached now would survive
        // the real owner later verifying or claiming it.
        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            return redirect()->route('connections.edit')->withErrors([
                'connections' => 'Verify your email address before connecting '.ucfirst($provider).'.',
            ]);
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
