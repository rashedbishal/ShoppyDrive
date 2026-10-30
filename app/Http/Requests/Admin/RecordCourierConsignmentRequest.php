<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecordCourierConsignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consignment_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
            'verified_in_portal' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'verified_in_portal.accepted' => 'Confirm that you found this consignment for this order in the courier portal.',
            'note.min' => 'Describe what you checked in the courier portal (at least 10 characters).',
        ];
    }
}
