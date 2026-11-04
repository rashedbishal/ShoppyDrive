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
    // FSA-20: no existing precedent anywhere in this codebase for a video
    // size limit — 50MB is a deliberate choice for a short product-demo
    // clip, not derived from an existing convention the way the 5MB image
    // limit is (matches BlogRequest's existing featured_image max:5120).
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const MAX_VIDEO_BYTES = 50 * 1024 * 1024;

    // Newly uploaded files only, per submission — never counts existing
    // media kept as plain string paths, so editing a product that already
    // has more than 10 media items is never blocked by this.
    private const MAX_NEW_UPLOADS = 10;

    private const MAX_SPECIFICATIONS = 30;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $hasVariants = $this->boolean('has_color_variant') || $this->boolean('has_size_variant');

        return [
            'stock_token' => [$productId !== null ? 'required' : 'prohibited', 'string'],
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
            'specifications' => ['nullable', 'array', 'max:'.self::MAX_SPECIFICATIONS],
            'specifications.*.key' => ['required_with:specifications', 'string', 'max:100'],
            'specifications.*.value' => ['required_with:specifications', 'string', 'max:255'],
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
            // FSA-20: counts only newly uploaded files (UploadedFile
            // instances) against the cap — a string is an existing media
            // path being kept as-is, never counted, so a product that
            // already has more than MAX_NEW_UPLOADS media items is never
            // blocked from being edited when no new file is added.
            'images' => ['nullable', 'array', function ($attribute, $value, $fail) {
                $newUploadCount = count(array_filter(
                    is_array($value) ? $value : [],
                    fn ($item) => $item instanceof UploadedFile,
                ));

                if ($newUploadCount > self::MAX_NEW_UPLOADS) {
                    $fail('No more than '.self::MAX_NEW_UPLOADS.' new images or videos may be uploaded in one submission.');
                }
            }],
            'images.*' => ['nullable', function ($attribute, $value, $fail) {
                if (! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail('The '.$attribute.' must be a valid string, image, or video file.');

                    return;
                }

                if (! $value instanceof UploadedFile) {
                    return;
                }

                // Explicit raster allowlist, not a blanket "image/*"
                // prefix check — that previously admitted
                // image/svg+xml, which is served straight from public
                // storage with no sanitization (stored XSS).
                // getMimeType() sniffs the real file content via finfo,
                // not the client-supplied filename or Content-Type header.
                $mime = (string) $value->getMimeType();
                $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $isImage = in_array($mime, $allowedImageMimes, true);
                $isVideo = str_starts_with($mime, 'video/');

                if (! $isImage && ! $isVideo) {
                    $fail('The '.$attribute.' must be a JPEG, PNG, WebP, GIF image, or a video.');

                    return;
                }

                // FSA-20: images and videos get different caps on the same
                // mixed-type field — a single uniform `max:` rule can't
                // express that, so the size check lives here, after the
                // type is already known.
                $maxBytes = $isImage ? self::MAX_IMAGE_BYTES : self::MAX_VIDEO_BYTES;

                if ($value->getSize() > $maxBytes) {
                    $fail($isImage
                        ? 'Image files must be 5MB or smaller.'
                        : 'Video files must be 50MB or smaller.');
                }
            }],
            'media_tags' => ['nullable', 'array'],
            'media_tags.*' => ['nullable', 'string'],
            'primary_image_index' => ['nullable', 'integer', 'min:0'],
            'hover_image_index' => ['nullable', 'integer', 'min:0'],
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
                //
                // A blank/whitespace-only SKU is treated as "no SKU" —
                // skipped here, and normalized to null at persistence
                // time (ProductController::syncVariants()) — so multiple
                // blank entries are never flagged as duplicates, the same
                // way multiple NULLs never collide under a unique index.
                $sku = trim((string) ($variant['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                // Keyed by a case-folded value: product_variants.sku's
                // actual column collation is utf8mb4_unicode_ci (case-
                // insensitive — confirmed against the live schema), so
                // "ABC" and "abc" are the same value as far as MySQL is
                // concerned. Without this, two variants in the *same*
                // submission differing only by case would pass this
                // in-PHP check yet still collide under a future unique
                // index.
                $normalizedSku = mb_strtolower($sku);
                if (isset($seenSkus[$normalizedSku])) {
                    $validator->errors()->add("variants.{$index}.sku", 'Duplicate SKU — each variant must have its own unique SKU.');
                }
                $seenSkus[$normalizedSku] = true;

                // Explicit LOWER() comparison, not where('sku', $sku) —
                // relying on the column's collation to make this
                // case-insensitive would make the check's behavior
                // silently depend on which database engine is running it
                // (confirmed: this does NOT match case-insensitively
                // under SQLite, which the test suite uses, even though it
                // does under the real MySQL column's actual collation).
                // Explicit LOWER() on both sides is deterministic on
                // every engine this app runs on.
                $existsElsewhere = ProductVariant::whereRaw('LOWER(sku) = ?', [$normalizedSku])
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
