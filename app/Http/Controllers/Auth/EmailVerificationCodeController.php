<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailVerificationCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $expired = ! $user->email_verification_code_expires_at
            || $user->email_verification_code_expires_at->isPast();

        if ($expired || ! $user->email_verification_code || ! Hash::check($request->string('code'), $user->email_verification_code)) {
            return redirect()->route('verification.notice')->withErrors([
                'code' => 'That code is incorrect or has expired. Please request a new one.',
            ]);
        }

        $user->markEmailAsVerified();
        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_sent_at' => null,
        ])->save();

        event(new Verified($user));

        // Only ever reached once per account — hasVerifiedEmail() above
        // returns early on any subsequent visit to this page.
        Mail::to($user->email)->send((new WelcomeMail($user))->afterCommit());

        return redirect()->intended(config('fortify.home'));
    }
}
