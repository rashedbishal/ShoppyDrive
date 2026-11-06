<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if ($user->anonymized_at !== null) {
            throw ValidationException::withMessages([
                'email' => 'This account is no longer active.',
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // SEC-008: whoever completes a reset has just proven they own the
        // inbox. On an account whose email was never verified, any Google or
        // Facebook link was attached by someone who never proved anything
        // (possibly not this owner), and social logins skip the email code
        // (FSA-09 policy), so such a link would keep logging in. Remove them;
        // the owner can reconnect in Settings › Connections once verified.
        $removeUnprovenLinks = ! $user->hasVerifiedEmail();

        DB::transaction(function () use ($user, $input, $removeUnprovenLinks): void {
            $user->forceFill([
                'password' => $input['password'],
                'has_password' => true,
            ])->save();

            if ($removeUnprovenLinks) {
                $user->socialAccounts()->delete();
            }
        });
    }
}
