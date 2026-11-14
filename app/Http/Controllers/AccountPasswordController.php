<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account > Change Password.
 *
 * DISC-31 (owner decision 2026-11-14): only for customers who have a
 * password. A customer who signed in only with Google/Facebook has none to
 * change, so both actions 404 for them; they set their first password in
 * Account > Connected Accounts (ConnectionsController::setPassword()).
 */
class AccountPasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->has_password, 404);

        return Inertia::render('account/ChangePassword');
    }

    /**
     * PasswordUpdateRequest::authorize() 404s for a customer without a password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
