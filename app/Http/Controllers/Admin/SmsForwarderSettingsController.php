<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SmsForwarderSettingsController extends Controller
{
    public function index(): Response
    {
        $token = config('services.sms_forwarder.token', '');
        $maskedToken = $this->maskToken((string) $token);

        return Inertia::render('admin/settings/sms-forwarder', [
            'maskedToken' => $maskedToken,
            'fullToken' => (string) $token,
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        $newToken = Str::random(64);

        $this->updateEnvValue('SMS_FORWARDER_TOKEN', $newToken);

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
