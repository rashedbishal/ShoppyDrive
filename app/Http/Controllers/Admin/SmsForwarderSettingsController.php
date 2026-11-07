<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SmsForwarderSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        // Read directly from .env so the value is always current,
        // regardless of whether the config cache is stale.
        $token = $this->readEnvValue('SMS_FORWARDER_TOKEN');
        $maskedToken = $this->maskToken($token);
        $canReveal = $request->user('admin')?->canAccess('sms_forwarder', 'update') ?? false;

        $props = ['maskedToken' => $maskedToken];
        if ($canReveal) {
            $props['fullToken'] = $token;
        }

        return Inertia::render('admin/settings/sms-forwarder', $props);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $newToken = Str::random(64);

        try {
            $this->updateEnvValue('SMS_FORWARDER_TOKEN', $newToken);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()->route('admin.settings.sms-forwarder.index')
                ->with('error', 'The token could not be saved, so the current one still works. Check the server has free disk space.');
        }

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) regenerated the SMS forwarder token.");

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
        $envPath = app()->environmentFilePath();

        if (! file_exists($envPath)) {
            return (string) config('services.sms_forwarder.token', '');
        }

        $content = (string) file_get_contents($envPath);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * SEC-024: .env is written beside itself and swapped in with a rename, so
     * a full disk or a crash leaves the old file whole instead of a cut-off
     * one that would take the site down.
     *
     * @throws RuntimeException when the file can't be safely replaced.
     */
    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = app()->environmentFilePath();
        $envContent = (string) file_get_contents($envPath);
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $line = $key.'='.$value;

        $updated = preg_match($pattern, $envContent) === 1
            ? (string) preg_replace_callback($pattern, fn (): string => $line, $envContent)
            : rtrim($envContent, "\r\n").PHP_EOL.$line.PHP_EOL;

        $temporaryPath = $envPath.'.'.Str::random(8).'.tmp';
        $bytesWritten = @file_put_contents($temporaryPath, $updated, LOCK_EX);

        if ($bytesWritten !== strlen($updated)
            || ! chmod($temporaryPath, fileperms($envPath) & 0777)
            || ! rename($temporaryPath, $envPath)) {
            @unlink($temporaryPath);

            throw new RuntimeException('The .env file could not be updated.');
        }
    }
}
