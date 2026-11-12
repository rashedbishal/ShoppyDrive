<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HeartbeatPing;
use App\Support\SecurityRateLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Security: the bot-protection limits in SecurityRateLimits. Any
 * admin can view them; only the mother admin can change them.
 */
class SecuritySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('admin/settings/security', [
            'limits' => SecurityRateLimits::all(),
            'bounds' => SecurityRateLimits::FIELDS,
            'canEdit' => (bool) Auth::guard('admin')->user()?->is_mother,
            'visitorIp' => $request->ip(),
            // OPS-002: a monitor ping URL works like a secret, so only the
            // mother admin (the only one who can change it) is ever sent it.
            'heartbeatPingUrl' => $this->isMother() ? HeartbeatPing::url() : null,
            'heartbeatConfigured' => HeartbeatPing::url() !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! Auth::guard('admin')->user()?->is_mother) {
            return back()->with('error', 'Only the mother admin can change security settings.');
        }

        $values = $request->validate(SecurityRateLimits::rules(), [
            '*.required' => 'Please fill in every box.',
            '*.integer' => 'Please enter whole numbers only.',
            '*.min' => 'This number is too low — it must be at least :min.',
            '*.max' => 'This number is too high — it can be at most :max.',
        ]);

        SecurityRateLimits::save(array_map('intval', $values));

        return redirect()->route('admin.settings.security.edit')->with('success', 'Security settings saved.');
    }

    /**
     * OPS-002: the URL `app:drain-queue` pings every minute so an outside
     * monitor can alert when cron stops. Mother-only, like the rest of this
     * page's saves.
     */
    public function updateHeartbeat(Request $request): RedirectResponse
    {
        if (! $this->isMother()) {
            return back()->with('error', 'Only the mother admin can change the cron monitoring URL.');
        }

        $validated = $request->validate([
            'heartbeat_ping_url' => ['nullable', 'url:http,https', 'max:300'],
        ], [
            'heartbeat_ping_url.url' => 'Enter the full ping URL from your monitoring service, starting with https://',
            'heartbeat_ping_url.max' => 'This URL is too long.',
        ]);

        HeartbeatPing::save($validated['heartbeat_ping_url'] ?? null);

        return redirect()->route('admin.settings.security.edit')->with('success', 'Cron monitoring ping saved.');
    }

    private function isMother(): bool
    {
        return (bool) Auth::guard('admin')->user()?->is_mother;
    }
}
