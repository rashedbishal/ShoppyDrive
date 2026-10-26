<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HeroSlideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Assuming authorization is handled by middleware or policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['required', 'string', 'url'], // Changed to 'url' assuming image is a URL string for now. Adjust if it's a file upload.
            'primary_cta_label' => ['nullable', 'string', 'max:100'],
            'primary_cta_href' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
            'stats' => ['nullable', 'array'],
        ];
    }
}
