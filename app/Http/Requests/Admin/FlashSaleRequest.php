<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0'],
            'items.*.stock_limit' => ['required', 'integer', 'min:1'],
        ];
    }
}
