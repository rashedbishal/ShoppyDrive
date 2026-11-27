<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Rules\RasterImageOrUrl;
use Closure;
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
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'unique:sub_categories,slug,'.($subCategoryId ?? 'NULL').',id,deleted_at,NULL',
                $this->notACategorySlug(...),
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
            'description' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'image' => ['nullable', new RasterImageOrUrl],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * DISC-89: /category/{slug} looks categories up first, so a sub-category
     * with a category's slug could never be reached. A sub-category keeping
     * its current slug is never blocked, so an older clash can't stop
     * unrelated edits.
     */
    private function notACategorySlug(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === $this->route('sub_category')?->slug) {
            return;
        }

        if (Category::where('slug', $value)->exists()) {
            $fail('A category already uses this slug. Category and sub-category pages share the same /category/ address, so choose a different slug.');
        }
    }
}
