<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use Illuminate\Foundation\Http\FormRequest;

class TrackIncompleteOrderRequest extends FormRequest
{
    use NormalizesBangladeshiPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attempt_token' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
