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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ])->after(function (ValidatorInstance $validator) use ($input): void {
            $this->guardAgainstDuplicateEmail($validator, $input['email'] ?? null);
        })->validate();

        $referredById = $this->referrals->resolveReferrerId(
            $input['referral_code'] ?? session('pending_referral_code'),
        );
        session()->forget('pending_referral_code');

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'has_password' => true,
            'referred_by_id' => $referredById,
        ]);
    }

    /**
     * Report a message tailored to how the colliding email was registered,
     * instead of a generic "already taken" error, so customers who signed
     * up via Google/Facebook know how to proceed.
     */
    private function guardAgainstDuplicateEmail(ValidatorInstance $validator, ?string $email): void
    {
        if (! $email) {
            return;
        }

        $existing = User::where('email', $email)->first();

        if (! $existing) {
            return;
        }

        if ($existing->has_password) {
            $validator->errors()->add('email', 'The email has already been taken.');

            return;
        }

        $providers = $existing->socialAccounts->pluck('provider')
            ->map(fn (string $provider): string => ucfirst($provider))
            ->implode(' or ');

        $validator->errors()->add('email', $providers !== ''
            ? "This email is already registered via {$providers}. Please sign in with {$providers}, or use \"Forgot password\" on the login page to set a password for email sign-in."
            : 'The email has already been taken.');
    }
}
