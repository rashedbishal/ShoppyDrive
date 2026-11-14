<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * DISC-31: a customer who signed in only with Google/Facebook has no
     * password to change; they set one in Account > Connected Accounts.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->has_password;
    }

    /**
     * 404 rather than 403, matching the Change Password page itself
     * (AccountPasswordController::edit()).
     */
    protected function failedAuthorization(): never
    {
        abort(404);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
