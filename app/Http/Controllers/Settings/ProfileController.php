<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\EmailChangeConfirmation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly EmailChangeConfirmation $emailChange,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $pending = $this->emailChange->pending($request);

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // SEC-009: an email change waiting for the code sent to the current address.
            'pendingEmailChange' => $pending === null ? null : [
                'new_email' => $pending['email'],
                'sent_to' => $request->user()->email,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // SEC-009: a verified account's email changes only after the code
        // sent to the current address is entered (confirmEmailChange()).
        if ($this->emailChange->requiresCode($request->user(), $validated['email'])) {
            $newEmail = $validated['email'];
            unset($validated['email']);
            $request->user()->fill($validated)->save();
            $this->emailChange->start($request, $request->user(), $newEmail);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent a 6-digit code to :email. Enter it below to confirm your new email.', ['email' => $request->user()->email])]);

            return to_route('profile.edit');
        }

        $request->user()->fill($validated);

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
     * SEC-009: applies a pending email change once the code sent to the
     * current address is entered. The new address must then be verified,
     * exactly as after any email change (SEC-003 reset below).
     */
    public function confirmEmailChange(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $result = $this->emailChange->verify($request, (string) $request->input('code'));

        if (isset($result['error'])) {
            return back()->withErrors(['code' => $result['error']]);
        }

        $user = $request->user();

        // The address may have been taken while the code was in transit.
        if (User::where('email', $result['email'])->whereKeyNot($user->id)->exists()) {
            return back()->withErrors(['code' => __('That email address is already used by another account.')]);
        }

        $user->forceFill([
            'email' => $result['email'],
            'email_verified_at' => null,
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_sent_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your email was changed. Please verify the new address.')]);

        return to_route('profile.edit');
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $this->emailChange->cancel($request);

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
