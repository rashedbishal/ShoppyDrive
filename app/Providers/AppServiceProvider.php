<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\SecurityRateLimits;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGoogleSocialite();
        $this->configureFacebookSocialite();
        $this->configureErrorPages();
        $this->configureRateLimiting();

        // Guard against double-registering on persistent-worker setups
        // (Octane, or a long-lived `artisan serve` process reusing the
        // event dispatcher across requests) — without this, every extra
        // boot() call stacks another listener, sending one email per
        // listener for a single registration.
        if (! Event::hasListeners(Registered::class)) {
            Event::listen(Registered::class, SendEmailVerificationNotification::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Render HTTP errors as the themed `errors/ErrorPage` Inertia page instead
     * of Laravel's default screens. Server errors (500/503) skip shared data —
     * the database or shared props may be exactly what failed — and 500s keep
     * Laravel's debug screen while APP_DEBUG is on. JSON/API requests are
     * left untouched.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            $status = $response->statusCode();
            $request = $response->request;

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return null;
            }

            if ($status === 500 && config('app.debug')) {
                return null;
            }

            if ($status >= 500) {
                // Shared props may already be registered (and may be what's failing) — drop them.
                Inertia::flushShared();
            }

            $errorPage = $response->render('errors/ErrorPage', [
                'status' => $status,
                'isAdminArea' => $request->is('admin', 'admin/*'),
            ]);

            return $status >= 500 ? $errorPage : $errorPage->withSharedData();
        });
    }

    /**
     * The admin guard is a separate, hand-rolled auth flow (see
     * AdminAuthController) that never goes through Fortify's pipeline, so
     * it doesn't get Fortify's 'login' rate limiter for free — registered
     * here instead, with the identical email+IP keying FortifyServiceProvider
     * uses for the customer 'login' limiter, so brute-forcing any one admin
     * account (including the is_mother super-admin, which this applies to
     * exactly like every other admin) is bounded the same way.
     */
    protected function configureRateLimiting(): void
    {
        // Deliberately hardcoded, not read from Admin > Security: this
        // guards the very page those settings are changed from. The second
        // limit caps one network trying many different admin emails.
        RateLimiter::for('admin-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        // Payment/courier/SMS notifications. Every caller is already
        // authenticated (re-verified with ZiniPay, HMAC-signed by Steadfast,
        // bearer token for the SMS phone), so this only stops junk floods.
        // Deliberately generous and hardcoded rather than in Admin > Security:
        // too strict a value here would drop real payment confirmations.
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(60)->by('ip:'.$request->ip()));

        // The bKash/Nagad SMS-forwarder phone: only requests with a wrong
        // token count, so the real phone is never blocked however many SMS
        // it forwards at once (e.g. a backlog after being offline) — a
        // dropped payment SMS would stop that order verifying automatically.
        RateLimiter::for('sms-forwarder', function (Request $request) {
            $expected = (string) config('services.sms_forwarder.token');
            $given = (string) $request->bearerToken();

            if ($expected !== '' && $given !== '' && hash_equals($expected, $given)) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        // Everything below is tunable from Admin > Security (see SecurityRateLimits).
        RateLimiter::for('place-order', function (Request $request) {
            $limits = SecurityRateLimits::all();
            $response = SecurityRateLimits::tooManyAttempts("You're placing orders too quickly. Please wait a few minutes and try again.");
            $phone = substr(preg_replace('/\D+/', '', (string) $request->input('phone')) ?? '', -10);

            return [
                Limit::perMinutes($limits['orders_per_network_minutes'], $limits['orders_per_network_times'])
                    ->by(SecurityRateLimits::visitorKey($request))
                    ->response($response),
                $phone === ''
                    ? Limit::none()
                    : Limit::perMinutes($limits['orders_per_phone_minutes'], $limits['orders_per_phone_times'])
                        ->by('phone:'.$phone)
                        ->response($response),
            ];
        });

        RateLimiter::for('payment-verify', function (Request $request) {
            $limits = SecurityRateLimits::all();
            $response = SecurityRateLimits::tooManyAttempts('Too many transaction ID attempts. Please wait a few minutes, double-check your transaction ID, and try again.');

            return [
                Limit::perMinutes($limits['payment_per_order_minutes'], $limits['payment_per_order_times'])
                    ->by('payment:'.$request->route('token'))
                    ->response($response),
                Limit::perMinutes($limits['payment_per_network_minutes'], $limits['payment_per_network_times'])
                    ->by(SecurityRateLimits::visitorKey($request))
                    ->response($response),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            $limits = SecurityRateLimits::all();

            return Limit::perMinutes($limits['signups_per_network_minutes'], $limits['signups_per_network_times'])
                ->by('ip:'.$request->ip())
                ->response(SecurityRateLimits::tooManyAttempts('Too many new accounts have been created from your network. Please try again later.'));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $limits = SecurityRateLimits::all();
            $response = SecurityRateLimits::tooManyAttempts('Too many password reset requests. Please wait a while and try again.');
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes($limits['password_reset_per_network_minutes'], $limits['password_reset_per_network_times'])
                    ->by('ip:'.$request->ip())
                    ->response($response),
                $email === ''
                    ? Limit::none()
                    : Limit::perMinutes($limits['password_reset_per_email_minutes'], $limits['password_reset_per_email_times'])
                        ->by('email:'.$email)
                        ->response($response),
            ];
        });

        RateLimiter::for('coupon-check', function (Request $request) {
            $limits = SecurityRateLimits::all();

            return Limit::perMinutes($limits['coupon_checks_minutes'], $limits['coupon_checks_times'])
                ->by(SecurityRateLimits::visitorKey($request))
                ->response(SecurityRateLimits::tooManyAttempts('Too many coupon tries. Please wait a minute and try again.'));
        });

        // Single-rule limits, each counted per shopper (signed-in customer,
        // else internet connection): limiter name => [settings prefix, message].
        $perShopperLimiters = [
            'contact-message' => ['contact_messages', 'Too many messages sent. Please wait a few minutes and try again.'],
            'newsletter-signup' => ['newsletter_signups', 'Too many newsletter requests. Please wait a few minutes and try again.'],
            'newsletter-check' => ['newsletter_checks', 'Too many requests. Please wait a minute and try again.'],
            'checkout-tracking' => ['checkout_tracking', 'Too many requests. Please wait a minute and try again.'],
            'order-tracking' => ['order_tracking', 'Too many order lookups. Please wait a minute and try again.'],
            'search-suggestions' => ['search_suggestions', 'Too many searches. Please wait a minute and try again.'],
            'product-browsing' => ['product_browsing', "You're browsing very quickly. Please wait a minute and try again."],
            'invoice-download' => ['invoice_downloads', 'Too many invoice downloads. Please wait a minute and try again.'],
            'review' => ['reviews', "You've posted several reviews recently. Please try again later."],
            'password-check' => ['password_checks', 'Too many password attempts. Please wait a minute and try again.'],
            'account-action' => ['account_actions', "You're doing that very quickly. Please wait a minute and try again."],
        ];

        foreach ($perShopperLimiters as $name => [$prefix, $message]) {
            RateLimiter::for($name, function (Request $request) use ($prefix, $message) {
                $limits = SecurityRateLimits::all();

                return Limit::perMinutes($limits[$prefix.'_minutes'], $limits[$prefix.'_times'])
                    ->by(SecurityRateLimits::visitorKey($request))
                    ->response(SecurityRateLimits::tooManyAttempts($message));
            });
        }
    }

    /**
     * Let CMS-configured Google OAuth credentials override the .env values,
     * so admins can manage them from the settings panel without a redeploy.
     */
    protected function configureGoogleSocialite(): void
    {
        try {
            $settings = Setting::whereIn('key', ['google_client_id', 'google_client_secret', 'google_redirect_uri'])
                ->pluck('value', 'key');
        } catch (Throwable) {
            return;
        }

        if ($settings->isEmpty()) {
            return;
        }

        config([
            'services.google.client_id' => $settings->get('google_client_id') ?: config('services.google.client_id'),
            'services.google.client_secret' => $settings->get('google_client_secret') ?: config('services.google.client_secret'),
            'services.google.redirect' => $settings->get('google_redirect_uri') ?: config('services.google.redirect'),
        ]);
    }

    /**
     * Let CMS-configured Facebook OAuth credentials override the .env values,
     * so admins can manage them from the settings panel without a redeploy.
     */
    protected function configureFacebookSocialite(): void
    {
        try {
            $settings = Setting::whereIn('key', ['facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri'])
                ->pluck('value', 'key');
        } catch (Throwable) {
            return;
        }

        if ($settings->isEmpty()) {
            return;
        }

        config([
            'services.facebook.client_id' => $settings->get('facebook_client_id') ?: config('services.facebook.client_id'),
            'services.facebook.client_secret' => $settings->get('facebook_client_secret') ?: config('services.facebook.client_secret'),
            'services.facebook.redirect' => $settings->get('facebook_redirect_uri') ?: config('services.facebook.redirect'),
        ]);
    }
}
