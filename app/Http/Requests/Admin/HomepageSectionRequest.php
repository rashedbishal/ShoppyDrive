<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'action_label' => ['nullable', 'string', 'max:40'],
            'action_href' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['required', 'boolean'],

            'metadata' => ['nullable', 'array'],
            'metadata.phone' => ['nullable', 'string', 'max:30'],
            'metadata.free_delivery_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'metadata.email' => ['nullable', 'email', 'max:150'],
            'metadata.autoplay_seconds' => ['nullable', 'integer', 'min:2', 'max:30'],

            // Featured products fields
            'featured_products' => ['nullable', 'array'],
            'featured_products.*.id' => ['required', 'integer', 'exists:products,id'],

            // New Arrivals / Best Sellers product ids
            'section_products' => ['nullable', 'array'],
            'section_products.*.id' => ['required', 'integer', 'exists:products,id'],

            // Brands section brand ids
            'section_brands' => ['nullable', 'array'],
            'section_brands.*.id' => ['required', 'integer', 'exists:brands,id'],
        ];
    }
}
