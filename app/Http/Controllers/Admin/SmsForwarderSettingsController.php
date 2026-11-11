<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SmsForwarderHeartbeat;
use App\Support\SmsForwarderToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsForwarderSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $token = SmsForwarderToken::current();
        $maskedToken = $this->maskToken($token);
        $canReveal = $request->user('admin')?->canAccess('sms_forwarder', 'update') ?? false;

        $props = ['maskedToken' => $maskedToken, 'forwarderStatus' => SmsForwarderHeartbeat::status()];
        if ($canReveal) {
            $props['fullToken'] = $token;
        }

        return Inertia::render('admin/settings/sms-forwarder', $props);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $newToken = SmsForwarderToken::regenerate();

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) regenerated the SMS forwarder token.");

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
}
