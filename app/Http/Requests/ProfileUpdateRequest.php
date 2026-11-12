<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Support\BangladeshiPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use NormalizesBangladeshiPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Only an account with no Google/Facebook login connected can
            // change its email (see after()); a connected one may still send
            // its current address, which changes nothing.
            'email' => $this->user()->canChangeEmail()
                ? ['required', 'string', 'email', 'max:150', Rule::unique('users')->ignore($this->user()->id)]
                : ['sometimes', 'nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.BangladeshiPhone::PATTERN],
            'avatar' => ['nullable', 'image', 'mimes:webp,jpeg,png', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => BangladeshiPhone::INVALID_MESSAGE];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $email = $this->input('email');

            if (! is_string($email) || $email === '' || $this->user()->canChangeEmail()) {
                return;
            }

            if (strcasecmp(trim($email), (string) $this->user()->email) !== 0) {
                $validator->errors()->add('email', __('Your email comes from your Google or Facebook sign-in, so it can\'t be changed here.'));
            }
        }];
    }
}
