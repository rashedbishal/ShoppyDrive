<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Models\User;
use App\Services\EmailChangeConfirmation;
use App\Services\ImageOptimizer;
use App\Support\UnusedUploadRemover;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's one profile page (Account > Profile): name, phone, photo,
 * email (changed with a code, SEC-009), cart-reminder emails, and deleting the
 * account. It replaced the separate starter-kit /settings pages, which were
 * not linked from anywhere a customer could find them.
 */
class AccountProfileController extends Controller
{
    public function __construct(
        private readonly EmailChangeConfirmation $emailChange,
    ) {}

    public function edit(Request $request): Response
    {
        $pending = $this->emailChange->pending($request);

        return Inertia::render('account/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // SEC-009: an email change waiting for the code sent to the current address.
            'pendingEmailChange' => $pending === null ? null : [
                'new_email' => $pending['email'],
                'sent_to' => $request->user()->email,
            ],
            // OPS-006: the column is NOT NULL, default on; `?? true` only
            // covers a user instance created in memory before that default.
            'cartRemindersEnabled' => (bool) ($request->user()->abandoned_cart_reminders_enabled ?? true),
        ]);
    }

    /**
     * Persists name/phone/avatar only. The email address is read-only on the
     * profile form and is never accepted here, whatever the request carries.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = [
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
        ];

        if ($request->hasFile('avatar')) {
            $this->deleteStoredAvatar($user->avatar);
            $data['avatar'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('avatar'), 'avatars');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteStoredAvatar($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        return to_route('account.profile');
    }

    /**
     * SEC-009: applies a pending email change once the code sent to the
     * current address is entered. The new address must then be verified,
     * exactly as after any email change (User::changeEmailTo()).
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

        $user->changeEmailTo($result['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your email was changed. Please verify the new address.')]);

        return to_route('account.profile');
    }

    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $this->emailChange->cancel($request);

        return to_route('account.profile');
    }

    /**
     * OPS-006: the email's unsubscribe link only turns cart reminders off;
     * this lets a customer see the setting and turn it back on as well.
     */
    public function updateCartReminders(Request $request): RedirectResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = $request->boolean('enabled');

        $request->user()->update(['abandoned_cart_reminders_enabled' => $enabled]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $enabled
            ? __('Cart reminder emails are on.')
            : __('Cart reminder emails are off.')]);

        return to_route('account.profile');
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
            // The avatar stays: anonymize() keeps it in the snapshot an admin
            // can restore the account from.
            $user->anonymize();
        } else {
            $avatar = $user->avatar;
            $user->forceDelete();
            // A social-login avatar is an outside address and is left alone.
            app(UnusedUploadRemover::class)->removeIfUnused($avatar);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Mirrors Admin\SettingController's logo-replace convention: stored
     * values are "/storage/..." paths, so the disk-relative portion is
     * whatever follows that prefix.
     */
    private function deleteStoredAvatar(?string $current): void
    {
        if ($current && str_starts_with($current, '/storage/')) {
            Storage::disk('public')->delete(substr($current, strlen('/storage/')));
        }
    }
}
