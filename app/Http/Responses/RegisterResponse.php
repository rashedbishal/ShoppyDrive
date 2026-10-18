<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Send a freshly registered but unverified user straight to the
     * verification-code screen instead of silently letting them land on the
     * homepage logged in but unverified.
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false, 'email_verified' => false], 201)
                : redirect()->route('verification.notice');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended(Fortify::redirects('register'));
    }
}
