<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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

        Event::listen(Registered::class, SendEmailVerificationNotification::class);
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
