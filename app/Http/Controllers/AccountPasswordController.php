<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Account > Change Password. The page itself is rendered by a route closure in
 * routes/account.php; this only saves the new password.
 */
class AccountPasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
