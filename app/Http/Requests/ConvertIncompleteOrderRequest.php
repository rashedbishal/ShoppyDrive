<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;

class ConvertIncompleteOrderRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:60'],
            'state' => ['required', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id', new VariantBelongsToProduct],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
        ];
    }
}
