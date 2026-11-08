<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use App\Services\ReferralService;
use App\Support\CartManager;
use App\Support\SecurityRateLimits;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        // SEC-015: the same answer whether or not the email has an account.
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthenticationHooks();
        $this->configureAuthenticationPipeline();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if (! $user || $user->anonymized_at !== null) {
                return null;
            }

            if (Hash::check($request->password, $user->password)) {
                return $user;
            }

            if (! $user->has_password) {
                $providers = $user->socialAccounts->pluck('provider')
                    ->map(fn (string $provider): string => ucfirst($provider))
                    ->implode(' or ');

                if ($providers !== '') {
                    throw ValidationException::withMessages([
                        Fortify::username() => ["This account uses {$providers} sign-in. Please continue with {$providers}, or use \"Forgot password\" to set a password for email sign-in."],
                    ]);
                }
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'resendAvailableAt' => $request->user()?->verificationCodeResendAvailableAt()?->toIso8601String(),
        ]));

        Fortify::registerView(function (Request $request) {
            $code = $request->filled('ref') ? trim((string) $request->query('ref')) : null;

            // A code typo'd in the URL (or a stale/revoked one) must not
            // silently show the "you get a discount" banner — only a code
            // that actually resolves to a user is treated as valid here,
            // reusing the same lookup CreateNewUser uses at registration
            // time so the two can never disagree.
            $referrerId = $code !== null ? app(ReferralService::class)->resolveReferrerId($code) : null;

            if ($referrerId !== null) {
                session(['pending_referral_code' => $code]);
            }

            return Inertia::render('auth/register', [
                'referralCode' => $code,
                'referralCodeValid' => $code !== null ? $referrerId !== null : null,
            ]);
        });

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower(SecurityRateLimits::textInput($request, Fortify::username())).'|'.$request->ip());

            $limits = SecurityRateLimits::all();

            return [
                Limit::perMinute(5)->by($throttleKey),
                // One network trying many different accounts (Admin > Security).
                Limit::perMinutes($limits['sign_in_per_network_minutes'], $limits['sign_in_per_network_times'])
                    ->by('ip:'.$request->ip())
                    ->response(SecurityRateLimits::tooManyAttempts('Too many sign-in attempts from your network. Please wait a minute and try again.')),
            ];
        });

        // Requesting a new verification code (resend button + the unused
        // link-click route): a short cooldown against double-clicks, plus a
        // hard cap per code lifetime against inbox-flooding abuse.
        RateLimiter::for('verification', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return [
                Limit::perMinute(1)->by($key)->response(fn (Request $request) => back()->withErrors([
                    'code' => 'Please wait a minute before requesting another code.',
                ])),
                Limit::perMinutes(15, 5)->by($key)->response(fn (Request $request) => back()->withErrors([
                    'code' => 'Too many code requests. Please try again in a few minutes.',
                ])),
            ];
        });

        // Entering a verification code: bounded tightly enough that
        // brute-forcing a 6-digit code within its 15-minute lifetime is
        // infeasible, without making legitimate typos frustrating.
        RateLimiter::for('verification-code', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return [
                Limit::perMinute(5)->by($key)->response(fn (Request $request) => back()->withErrors([
                    'code' => 'Too many attempts. Please wait a minute and try again.',
                ])),
                Limit::perMinutes(15, 10)->by($key)->response(fn (Request $request) => back()->withErrors([
                    'code' => 'Too many attempts. Please request a new code and try again shortly.',
                ])),
            ];
        });
    }

    /**
     * Product decision (SEC-014): two-factor authentication doesn't exist in
     * this app, for customers or admins. Password credentials alone log in;
     * only email verification by OTP applies, to manual registration. This
     * is the standard Fortify login pipeline without
     * `RedirectsIfTwoFactorAuthenticatable`, so an account with an old 2FA
     * secret on it is never challenged.
     */
    private function configureAuthenticationPipeline(): void
    {
        Fortify::authenticateThrough(fn (Request $request): array => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    private function configureAuthenticationHooks(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            /** @var Request|null $request */
            $request = request();

            if ($request === null) {
                return;
            }

            app(CartManager::class)->mergeSessionCartIntoUserCart(
                $event->user,
                $request->session()->getId(),
            );
        });
    }
}
