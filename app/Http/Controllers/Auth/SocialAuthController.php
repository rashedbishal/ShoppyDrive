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

            if ($user) {
                // An existing account matched purely by email, with no prior
                // link to this exact provider identity — the email itself is
                // the only thing being trusted here (SEC-01). Google exposes
                // an explicit `email_verified` claim in its raw payload (not
                // surfaced by any SocialiteUser contract method, so it has to
                // be read from getRaw() directly); when Google itself says
                // the email is unverified, that is not enough proof of
                // ownership to auto-link an existing account. Facebook
                // exposes no equivalent claim at all, so this check is
                // deliberately Google-only. A missing/null claim (every
                // provider other than Google, and Google responses that
                // simply don't include it) is never treated as proof either
                // way — only an explicit `false` blocks the link.
                if ($provider === 'google' && ($socialUser->getRaw()['email_verified'] ?? null) === false) {
                    return null;
                }
            } else {
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
        });

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'An account with this email already exists. Please log in with your password or an already-connected provider, then connect Google from your account settings.',
            ]);
        }

        session()->forget('pending_referral_code');

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
