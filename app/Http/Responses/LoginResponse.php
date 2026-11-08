<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send an unverified user straight to the verification-code screen
     * instead of silently letting them land on the homepage unverified.
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? response()->json(['email_verified' => false])
                : redirect()->route('verification.notice');
        }

        return $request->wantsJson()
            ? response()->json(['email_verified' => true])
            : redirect()->intended(Fortify::redirects('login'));
    }
}
