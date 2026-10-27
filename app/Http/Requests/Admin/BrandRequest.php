<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\RasterImageOrUrl;
use Illuminate\Foundation\Http\FormRequest;

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
            'logo' => ['nullable', new RasterImageOrUrl],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
