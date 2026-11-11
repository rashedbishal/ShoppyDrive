<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\SmsForwarderHeartbeat;
use App\Support\SmsForwarderToken;
use App\Support\WebPushConfig;
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

        $props = [
            'maskedToken' => $maskedToken,
            'forwarderStatus' => SmsForwarderHeartbeat::status(),
            // The VAPID keys themselves never go to the browser from here; the
            // public one reaches admins through the `vapid_public_key` shared prop.
            'pushStatus' => [
                'configured' => WebPushConfig::isConfigured(),
                'subscribed_browsers' => PushSubscription::count(),
                'can_manage' => (bool) $request->user('admin')?->is_mother,
            ],
        ];
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

    /**
     * Creates (or replaces) the key pair browsers need for instant SMS
     * notifications. Mother admin only, enforced by the route middleware.
     */
    public function generatePushKeys(Request $request): RedirectResponse
    {
        $hadKeys = WebPushConfig::isConfigured();
        $removed = WebPushConfig::generate();

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) ".($hadKeys ? 'regenerated' : 'generated').' the SMS notification (web push) keys.');

        return redirect()->route('admin.settings.sms-forwarder.index')->with(
            'success',
            $hadKeys
                ? "New notification keys created. {$removed} browser(s) must press \"Enable\" again."
                : 'Notification keys created. Reload the page and press "Enable" in the bottom-right banner, then allow notifications in the browser.',
        );
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 8).substr($token, -8);
    }
}
