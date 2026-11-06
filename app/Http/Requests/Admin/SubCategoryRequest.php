<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\RasterImageOrUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subCategory = $this->route('sub_category');
        $subCategoryId = $subCategory?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:sub_categories,slug,'.($subCategoryId ?? 'NULL').',id,deleted_at,NULL',
            ],
            // A sub-category must not be (re-)created under an inactive
            // parent — but editing one that's already under a category
            // deactivated since it was created must not break on an
            // unrelated field change, so its own current category_id is
            // still allowed even if that category is now inactive.
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->when($subCategory?->category_id, fn ($query, $currentId) => $query->orWhere('id', $currentId))),
            ],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', new RasterImageOrUrl],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
