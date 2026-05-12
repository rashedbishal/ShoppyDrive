<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:products,slug,'.($productId ?? 'NULL').',id,deleted_at,NULL',
            ],
            'sku' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sub_category_id' => ['required', 'integer', 'exists:sub_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'free_delivery' => ['boolean'],
            'free_delivery_min_qty' => ['nullable', 'integer', 'min:1'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', function ($attribute, $value, $fail) {
                if (! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail('The '.$attribute.' must be a valid string, image, or video file.');
                }
                if ($value instanceof UploadedFile) {
                    $mime = $value->getMimeType();
                    if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/')) {
                        $fail('The '.$attribute.' must be an image or a video.');
                    }
                }
            }],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
