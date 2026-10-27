<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
}
