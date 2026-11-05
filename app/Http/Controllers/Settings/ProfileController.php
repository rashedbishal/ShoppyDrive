<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            // SEC-003: a code already sent to the old address must not stay
            // valid for the new one — EmailVerificationCodeController only
            // ever checks the code against the user row, with nothing
            // tying it to which address it was actually sent to. Without
            // this, entering that stale code here would mark the new
            // (unproven) address verified, and a later "Continue with
            // Google" for that address's real owner would land them in
            // this account instead, where the attacker still holds the
            // password.
            $request->user()->email_verified_at = null;
            $request->user()->email_verification_code = null;
            $request->user()->email_verification_code_expires_at = null;
            $request->user()->email_verification_code_sent_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile. DB-001 (L4): an account with orders or other
     * retained business history is anonymized instead of deleted, so order,
     * payment, loyalty, coupon and return records stay intact; only an
     * account with none of that is permanently deleted.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        if ($user->hasRetainedHistory()) {
            $user->anonymize();
        } else {
            $user->forceDelete();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
