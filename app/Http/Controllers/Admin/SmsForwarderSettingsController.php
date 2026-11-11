<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\SmsForwarderHeartbeat;
use App\Support\SmsForwarderToken;
use App\Support\WebPushConfig;
use App\Support\WebPushSender;
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
                'last_result' => WebPushConfig::lastResult(),
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
                ? ($removed > 0
                    ? "New notification keys created. {$removed} browser(s) that had notifications on were reset: each must press \"Enable notifications on this browser\" again."
                    : 'New notification keys created. No browser had notifications on yet. Now press "Enable notifications on this browser" below.')
                : 'Notification keys created. Now press "Enable notifications on this browser" below and allow notifications in the browser.',
        );
    }

    /**
     * Sends one real push to the signed-in admin's own enabled browsers and
     * says what happened, so a broken setup shows its reason here instead of
     * as silence.
     */
    public function sendTestPush(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        if (! WebPushConfig::isConfigured()) {
            return back()->with('error', 'Notification keys are not set up yet. Generate them first.');
        }

        if ($admin->pushSubscriptions()->doesntExist()) {
            return back()->with('error', 'This account has no browser with notifications enabled. Press "Enable notifications on this browser" first.');
        }

        $result = WebPushSender::send($admin, [
            'title' => 'Test notification',
            'body' => 'If you can see this, SMS notifications work on this browser.',
            'url' => route('admin.settings.sms-forwarder.index'),
            'data' => ['type' => 'test'],
        ], 'test');

        if ($result['sent'] > 0 && $result['failed'] === []) {
            return back()->with('success', "Test notification sent to {$result['sent']} browser(s). If nothing appeared, check this computer's notification settings (Do Not Disturb) and the browser's site settings.");
        }

        if ($result['sent'] === 0 && $result['expired'] > 0 && $result['failed'] === []) {
            return back()->with('error', 'The browser sign-up had expired and was removed. Press "Enable notifications on this browser" again.');
        }

        return back()->with('error', 'Test notification failed: '.($result['failed'][0] ?? 'unknown error'));
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 8).substr($token, -8);
    }
}
