<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Services\LoyaltyPointsService;
use App\Support\PostLoginRedirect;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailVerificationCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return PostLoginRedirect::redirect(config('fortify.home'));
        }

        $request->validate([
            'code' => ['required', 'string', 'max:6'],
        ]);

        if (! $user->consumeEmailVerificationCode($request->string('code')->toString())) {
            // A duplicate submit lost the race to the one that verified.
            if ($user->hasVerifiedEmail()) {
                return PostLoginRedirect::redirect(config('fortify.home'));
            }

            return redirect()->route('verification.notice')->withErrors([
                'code' => 'That code is incorrect or has expired. Please request a new one.',
            ]);
        }

        event(new Verified($user));

        // BIZ-024: the sign-up bonus waits for a proven email (idempotent).
        app(LoyaltyPointsService::class)->grantRegistrationBonus($user);

        // Only ever reached once per account — hasVerifiedEmail() above
        // returns early on any subsequent visit to this page.
        Mail::to($user->email)->send((new WelcomeMail($user))->afterCommit());

        return PostLoginRedirect::redirect(config('fortify.home'));
    }
}
