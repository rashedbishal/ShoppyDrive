<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
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

    /** Largest value the decimal(10, 2) price columns can hold; above it the database throws instead of validating. */
    private const MAX_MONEY = 99999999.99;

    /** Largest value the signed 32-bit integer stock columns can hold. */
    private const MAX_INTEGER = 2147483647;

    /** Most units of stock one product or variant can hold (7 digits fit every stock column and list). */
    private const MAX_STOCK = 9999999;

    /** Attributes one product can build variants from (each multiplies the combinations). */
    private const MAX_VARIANT_ATTRIBUTES = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        // The older Color/Size flags still count, for callers that check the
        // rules without the translation in prepareForValidation().
        $hasVariants = (is_array($this->input('variant_attribute_ids')) && $this->input('variant_attribute_ids') !== [])
            || $this->boolean('has_color_variant')
            || $this->boolean('has_size_variant');

        return [
            'stock_token' => [$productId !== null ? 'required' : 'prohibited', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'unique:products,slug,'.($productId ?? 'NULL').',id,deleted_at,NULL',
            ],
            'sku' => ['nullable', 'string', 'max:50'],
            'short_description' => ['required', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:20000', function ($attribute, $value, $fail) {
                // The rich-text editor leaves markup such as <p><br></p> behind
                // when emptied, so "required" must look at the visible text.
                $text = trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags((string) $value))));

                if ($text === '') {
                    $fail('The description field is required.');
                }
            }],
            'specifications' => ['nullable', 'array', 'max:'.self::MAX_SPECIFICATIONS],
            'specifications.*.key' => ['required_with:specifications', 'string', 'max:60'],
            'specifications.*.value' => ['required_with:specifications', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sub_category_id' => ['required', 'integer', 'exists:sub_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            // BIZ-020: with variants this field is hidden and derived from them.
            'price' => [Rule::requiredIf(! $hasVariants), 'nullable', 'numeric', $hasVariants ? 'min:0' : 'gt:0', 'max:'.self::MAX_MONEY],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_MONEY],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_MONEY],
            'stock_quantity' => [Rule::requiredIf(! $hasVariants), 'nullable', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'free_delivery' => ['boolean'],
            'free_delivery_min_qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'has_color_variant' => ['boolean'],
            'has_size_variant' => ['boolean'],
            // The attributes this product's variants are built from, in the
            // order shown to customers (e.g. [Color, Set]).
            'variant_attribute_ids' => ['present', 'array', 'max:'.self::MAX_VARIANT_ATTRIBUTES],
            'variant_attribute_ids.*' => ['integer', 'distinct', 'exists:attributes,id'],
            'variants' => [Rule::requiredIf($hasVariants), 'array'],
            // One value per variant attribute, e.g. [Red, 12pcs].
            'variants.*.value_ids' => [Rule::requiredIf($hasVariants), 'array'],
            'variants.*.value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'variants.*.color_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'variants.*.size_value_id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'variants.*.sku' => ['nullable', 'string', 'max:50'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'gt:0', 'max:'.self::MAX_MONEY],
            'variants.*.compare_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_MONEY],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_MONEY],
            'variants.*.stock_quantity' => ['required_with:variants', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'variants.*.is_default' => ['boolean'],
            'cry_baby_enabled' => ['boolean'],
            // FSA-20: counts only newly uploaded files (UploadedFile
            // instances) against the cap — a string is an existing media
            // path being kept as-is, never counted, so a product that
            // already has more than MAX_NEW_UPLOADS media items is never
            // blocked from being edited when no new file is added.
            // A new product needs a picture; an update that sends no `images` leaves
            // the existing media alone, so it only has to keep at least one if it sends any.
            'images' => [$productId === null ? 'required' : 'sometimes', 'array', 'min:1', function ($attribute, $value, $fail) {
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
            'media_tags.*' => ['nullable', 'string', 'max:50'],
            'primary_image_index' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_INTEGER],
            'hover_image_index' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_INTEGER],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            // Admin-entered English equivalent(s) for a product named in
            // Bangla (e.g. "shirt, t-shirt" on শার্ট) — see ProductSearchQuery.
            'meta_keywords' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * A form opened before custom attributes existed (or an older client)
     * still sends Color/Size as `has_color_variant`/`has_size_variant` plus
     * `color_value_id`/`size_value_id` per variant. That is translated into
     * the general shape here; a switched-off dimension's value is ignored, as
     * before. A form that sends `variant_attribute_ids` is used as is.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('variant_attribute_ids')) {
            return;
        }

        $variants = $this->input('variants');

        // The attribute the submitted colour (or size) values belong to;
        // the one named Color (Size) only when no value was sent.
        $attributeOf = function (string $column, string $name) use ($variants): ?int {
            $valueId = collect(is_array($variants) ? $variants : [])
                ->map(fn ($variant) => is_array($variant) ? ($variant[$column] ?? null) : null)
                ->first(fn ($id) => $id !== null && $id !== '');
            $attributeId = $valueId !== null ? AttributeValue::whereKey($valueId)->value('attribute_id') : null;

            return $attributeId ?? Attribute::where('name', $name)->orderBy('id')->value('id');
        };

        $legacy = array_filter([
            'color' => $this->boolean('has_color_variant') ? $attributeOf('color_value_id', 'Color') : null,
            'size' => $this->boolean('has_size_variant') ? $attributeOf('size_value_id', 'Size') : null,
        ]);

        $this->merge(['variant_attribute_ids' => array_values(array_map('intval', $legacy))]);

        if (is_array($variants)) {
            $this->merge(['variants' => array_map(fn ($variant) => is_array($variant) ? [
                ...$variant,
                'value_ids' => array_values(array_filter([
                    isset($legacy['color']) ? ($variant['color_value_id'] ?? null) : null,
                    isset($legacy['size']) ? ($variant['size_value_id'] ?? null) : null,
                ], fn ($id) => $id !== null && $id !== '')),
            ] : $variant, $variants)]);
        }
    }

    /**
     * Friendly field names, so the messages shown above the form say which
     * field they are about.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'short_description' => 'short description',
            'description' => 'description',
            'images' => 'product image',
            'category_id' => 'category',
            'sub_category_id' => 'sub-category',
            'stock_quantity' => 'stock quantity',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Add at least one product image.',
            'images.min' => 'Add at least one product image.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $variants = $this->input('variants', []);

            if (! is_array($variants) || $variants === []) {
                return;
            }

            $attributeIds = array_map('intval', (array) $this->input('variant_attribute_ids', []));
            $valueAttributes = AttributeValue::query()
                ->whereKey(collect($variants)->flatMap(fn ($variant) => is_array($variant['value_ids'] ?? null) ? $variant['value_ids'] : [])->unique()->all())
                ->pluck('attribute_id', 'id');

            $defaultCount = 0;
            $seenCombos = [];
            $seenSkus = [];
            $productId = $this->route('product')?->id;

            foreach ($variants as $index => $variant) {
                $isDefault = filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($isDefault) {
                    $defaultCount++;
                }

                // Exactly one value for each of the product's variant
                // attributes, e.g. a colour and a set — no more, no less.
                $valueIds = array_map('intval', is_array($variant['value_ids'] ?? null) ? $variant['value_ids'] : []);
                $coveredAttributes = array_map(fn (int $valueId): int => (int) ($valueAttributes[$valueId] ?? 0), $valueIds);
                sort($coveredAttributes);
                $expectedAttributes = $attributeIds;
                sort($expectedAttributes);

                if ($coveredAttributes !== $expectedAttributes) {
                    $validator->errors()->add("variants.{$index}", 'Each variant needs exactly one value for every variant attribute.');
                }

                sort($valueIds);
                $combo = implode('-', $valueIds);
                if (isset($seenCombos[$combo])) {
                    $validator->errors()->add("variants.{$index}", 'Duplicate combination — each variant must be a different combination of values.');
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
