<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public const string EMAIL_UNAVAILABLE_MESSAGE = "We couldn't create an account with this email. Try signing in or resetting your password.";

    public function __construct(
        private readonly ReferralService $referrals,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => $this->passwordRules(),
        ])->after(function (ValidatorInstance $validator) use ($input): void {
            $this->guardAgainstDuplicateEmail($validator, $input['email'] ?? null);
        })->validate();

        // DISC-30 (owner decision 2026-11-14): referrals come only from the
        // referral link, which the register page stashes in the session when
        // it is valid. A code sent in the form is ignored.
        $referredById = $this->referrals->resolveReferrerId(session('pending_referral_code'));
        session()->forget('pending_referral_code');

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'has_password' => true,
            'referred_by_id' => $referredById,
        ]);

        // BIZ-024: the sign-up bonus is granted once the email is verified
        // (EmailVerificationCodeController), not here, so throwaway sign-ups
        // that never verify get no points.

        return $user;
    }

    /**
     * SEC-015: one message however the existing account was created, so
     * sign-up never reveals which sign-in method an email uses.
     */
    private function guardAgainstDuplicateEmail(ValidatorInstance $validator, ?string $email): void
    {
        if ($email && User::where('email', $email)->exists()) {
            $validator->errors()->add('email', self::EMAIL_UNAVAILABLE_MESSAGE);
        }
    }
}
