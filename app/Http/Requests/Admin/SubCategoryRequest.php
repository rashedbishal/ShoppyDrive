<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\RasterImageOrUrl;
use Illuminate\Foundation\Http\FormRequest;

class SubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subCategoryId = $this->route('sub_category')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:sub_categories,slug,'.($subCategoryId ?? 'NULL').',id,deleted_at,NULL',
            ],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', new RasterImageOrUrl],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
