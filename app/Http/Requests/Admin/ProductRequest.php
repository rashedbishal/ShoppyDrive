<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $hasVariants = $this->boolean('has_color_variant') || $this->boolean('has_size_variant');

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
            'price' => [Rule::requiredIf(! $hasVariants), 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => [Rule::requiredIf(! $hasVariants), 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'free_delivery' => ['boolean'],
            'free_delivery_min_qty' => ['nullable', 'integer', 'min:1'],
            'has_color_variant' => ['boolean'],
            'has_size_variant' => ['boolean'],
            'variants' => [Rule::requiredIf($hasVariants), 'array'],
            'variants.*.color_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'variants.*.size_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.compare_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['boolean'],
            'cry_baby_enabled' => ['boolean'],
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
            'media_tags' => ['nullable', 'array'],
            'media_tags.*' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $variants = $this->input('variants', []);

            if (! is_array($variants) || $variants === []) {
                return;
            }

            $defaultCount = 0;
            $seenCombos = [];

            foreach ($variants as $index => $variant) {
                $isDefault = filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($isDefault) {
                    $defaultCount++;
                }

                $combo = ($variant['color_value_id'] ?? 'null').'-'.($variant['size_value_id'] ?? 'null');
                if (isset($seenCombos[$combo])) {
                    $validator->errors()->add("variants.{$index}", 'Duplicate color/size combination — each pair must be unique.');
                }
                $seenCombos[$combo] = true;
            }

            if ($defaultCount !== 1) {
                $validator->errors()->add('variants', 'Exactly one variant must be marked as the default.');
            }
        });
    }
}
