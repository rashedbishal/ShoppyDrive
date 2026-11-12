<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CouponAppliesTo;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Rules\StorableDateTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => ['required', 'string', 'max:30', 'unique:coupons,code,'.($couponId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0', Rule::when($this->input('type') === 'percent', ['max:100'], ['max:99999999.99'])],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date', new StorableDateTime],
            'expires_at' => ['nullable', 'date', new StorableDateTime, 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'applies_to' => ['sometimes', Rule::enum(CouponAppliesTo::class)],
            'target_category_ids' => ['nullable', 'array'],
            'target_category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'target_sub_category_ids' => ['nullable', 'array'],
            'target_sub_category_ids.*' => ['integer', 'distinct', 'exists:sub_categories,id'],
            'target_products' => ['nullable', 'array'],
            'target_products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'target_products.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ];
    }

    /**
     * For a selected-items coupon: it needs something selected, a variant must
     * belong to its product, and nothing may be listed twice. That includes
     * listing something already covered by a broader row, such as a variant of
     * a whole product or a subcategory of a selected category. A product inside
     * a selected category is allowed, since it can only get one discount.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('applies_to') !== CouponAppliesTo::SELECTED->value) {
                return;
            }

            $categoryIds = array_map('intval', $this->input('target_category_ids', []) ?? []);
            $subCategoryIds = array_map('intval', $this->input('target_sub_category_ids', []) ?? []);
            $targets = $this->input('target_products', []) ?? [];

            if ($categoryIds === [] && $subCategoryIds === [] && $targets === []) {
                $validator->errors()->add('applies_to', 'Select at least one category, subcategory or product.');

                return;
            }

            if ($categoryIds !== [] && SubCategory::whereIn('id', $subCategoryIds)->whereIn('category_id', $categoryIds)->exists()) {
                $validator->errors()->add('target_sub_category_ids', 'A subcategory of a selected category is already covered.');
            }

            $variants = ProductVariant::whereIn('id', array_filter(array_column($targets, 'variant_id')))->pluck('product_id', 'id');
            $seenRows = [];
            $wholeProducts = [];
            $variantProducts = [];

            foreach ($targets as $index => $target) {
                $productId = (int) $target['product_id'];
                $variantId = empty($target['variant_id']) ? null : (int) $target['variant_id'];
                $row = $productId.':'.($variantId ?? 'whole');

                if (isset($seenRows[$row])) {
                    $validator->errors()->add("target_products.{$index}.product_id", 'This product or variant was already added.');

                    continue;
                }

                $seenRows[$row] = true;

                if ($variantId !== null && (int) ($variants[$variantId] ?? 0) !== $productId) {
                    $validator->errors()->add("target_products.{$index}.variant_id", 'This variant does not belong to the selected product.');

                    continue;
                }

                if ($variantId === null) {
                    $wholeProducts[$productId] = $index;
                } else {
                    $variantProducts[$productId] = $index;
                }
            }

            foreach (array_intersect_key($variantProducts, $wholeProducts) as $index) {
                $validator->errors()->add("target_products.{$index}.variant_id", 'The whole product is already added, which covers this variant.');
            }
        }];
    }
}
