<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SmsForwarderSettingsController extends Controller
{
    public function index(): Response
    {
        // Read directly from .env so the value is always current,
        // regardless of whether the config cache is stale.
        $token = $this->readEnvValue('SMS_FORWARDER_TOKEN');
        $maskedToken = $this->maskToken($token);

        return Inertia::render('admin/settings/sms-forwarder', [
            'maskedToken' => $maskedToken,
            'fullToken' => $token,
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        $newToken = Str::random(64);

        $this->updateEnvValue('SMS_FORWARDER_TOKEN', $newToken);

        // Bust config cache so the new token is used immediately for auth checks.
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            Artisan::call('config:cache');
        }

        return redirect()->route('admin.settings.sms-forwarder.index')
            ->with('newToken', $newToken)
            ->with('success', 'Token regenerated successfully. Copy the new token and update your Android app.');
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 8).substr($token, -8);
    }

    private function readEnvValue(string $key): string
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return (string) config('services.sms_forwarder.token', '');
        }

        $content = (string) file_get_contents($envPath);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, $key.'=')) {
            $envContent = preg_replace(
                '/^'.preg_quote($key, '/').'=.*/m',
                $key.'='.$value,
                $envContent,
            );
        } else {
            $envContent .= PHP_EOL.$key.'='.$value;
        }

        file_put_contents($envPath, $envContent);
    }
}
