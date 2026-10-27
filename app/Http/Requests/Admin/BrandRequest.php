<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\RasterImageOrUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:brands,slug,'.($brandId ?? 'NULL').',id'],
            // max:1024 (KB) only applies to a fresh upload — when the field is
            // the existing stored path string, `max` would instead check its
            // character length.
            'logo' => ['nullable', new RasterImageOrUrl, Rule::when($this->hasFile('logo'), ['max:1024'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.max' => 'The logo must be 1MB or smaller.',
        ];
    }
}
