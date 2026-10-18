<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionsController extends Controller
{
    /**
     * Show the user's connected login methods.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/connections', [
            'hasPassword' => $user->has_password,
            'connections' => $user->socialAccounts->map(fn (SocialAccount $account) => [
                'id' => $account->id,
                'provider' => $account->provider,
                'connected_at' => $account->created_at,
            ]),
        ]);
    }

    /**
     * Let a customer who signed up via a social provider set a password for
     * the first time, so email/password sign-in also works for them.
     */
    public function setPassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->has_password) {
            abort(403);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'has_password' => true,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password set. You can now sign in with your email and password too.']);

        return back();
    }

    /**
     * Disconnect a social provider, as long as the user has another way to
     * sign back in afterwards (a password, or another connected provider).
     */
    public function destroy(Request $request, SocialAccount $socialAccount): RedirectResponse
    {
        $user = $request->user();

        if ($socialAccount->user_id !== $user->id) {
            abort(403);
        }

        if (! $user->has_password && $user->socialAccounts()->count() <= 1) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'You need at least one way to sign in. Set a password or connect another provider before removing this one.',
            ]);

            return back();
        }

        $socialAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($socialAccount->provider).' account disconnected.']);

        return back();
    }
}
