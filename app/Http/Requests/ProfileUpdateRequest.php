<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Support\BangladeshiPhone;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
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
}
