<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCourierNotCreatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm that no consignment exists for this order in the courier portal.',
            'note.min' => 'Describe what you checked in the courier portal (at least 10 characters).',
        ];
    }
}
