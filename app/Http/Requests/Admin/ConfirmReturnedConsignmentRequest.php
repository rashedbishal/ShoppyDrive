<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReturnedConsignmentRequest extends FormRequest
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
            'confirmed.accepted' => 'Confirm that this order should be linked to the consignment the courier returned.',
            'note.min' => 'Describe why you are linking this consignment (at least 10 characters).',
        ];
    }
}
