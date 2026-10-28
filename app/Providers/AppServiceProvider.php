<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Spatie\Activitylog\Facades\CauserResolver;
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
        $this->configureActivityLogCauser();

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
        RateLimiter::for('admin-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * The audit trail's activity log needs to attribute every entry to
     * whichever of the app's two guards is actually authenticated — an
     * admin acting in the back office, or a customer placing their own
     * order on the storefront. Spatie's package only auto-resolves a
     * single configured guard by default, so this overrides that
     * resolution to check both, admin first since admin actions on a
     * customer's own records (e.g. changing an order's status) should
     * attribute to the admin, not whichever guard happens to be checked
     * first by coincidence.
     */
    protected function configureActivityLogCauser(): void
    {
        CauserResolver::resolveUsing(fn () => Auth::guard('admin')->user() ?? Auth::guard('web')->user());
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
