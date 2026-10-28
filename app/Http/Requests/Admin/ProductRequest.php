<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ProductVariant;
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
            'price' => [Rule::requiredIf(! $hasVariants), 'nullable', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => [Rule::requiredIf(! $hasVariants), 'nullable', 'integer', 'min:0'],
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
                    // Explicit raster allowlist, not a blanket "image/*"
                    // prefix check — that previously admitted
                    // image/svg+xml, which is served straight from public
                    // storage with no sanitization (stored XSS).
                    $mime = $value->getMimeType();
                    $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (! in_array($mime, $allowedImageMimes, true) && ! str_starts_with($mime, 'video/')) {
                        $fail('The '.$attribute.' must be a JPEG, PNG, WebP, GIF image, or a video.');
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
            $seenSkus = [];
            $productId = $this->route('product')?->id;

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

                // DB-001/CAT-001: product_variants.sku still has no
                // uniqueness enforcement at the database level — the
                // handful of duplicates that previously existed have
                // since been resolved, but a DB-level unique index still
                // can't be safely added purely from this validation
                // change alone (that's a separate, deliberate migration).
                // This guard stops new duplicates from being created from
                // here on, both within one submission and across
                // different products, without touching any existing row.
                $sku = trim((string) ($variant['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                if (isset($seenSkus[$sku])) {
                    $validator->errors()->add("variants.{$index}.sku", 'Duplicate SKU — each variant must have its own unique SKU.');
                }
                $seenSkus[$sku] = true;

                $existsElsewhere = ProductVariant::where('sku', $sku)
                    ->when($productId, fn ($q) => $q->where('product_id', '!=', $productId))
                    ->exists();

                if ($existsElsewhere) {
                    $validator->errors()->add("variants.{$index}.sku", 'This SKU is already used by a variant on a different product.');
                }
            }

            if ($defaultCount !== 1) {
                $validator->errors()->add('variants', 'Exactly one variant must be marked as the default.');
            }
        });
    }
}
