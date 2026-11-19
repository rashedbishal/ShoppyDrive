<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\NormalizesBangladeshiPhone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderShippingAddressRequest extends FormRequest
{
    use NormalizesBangladeshiPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // DISC-75 (owner request 2026-11-19): the recipient name is now
            // editable too; same limit as checkout (PlaceOrderRequest).
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
        ];
    }
}
