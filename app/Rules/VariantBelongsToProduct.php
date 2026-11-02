<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * FSA-01: a submitted `variant_id` must belong to the `product_id` submitted
 * beside it. Price is resolved from the product (a product-wide flash sale
 * matches any variant id) while stock, SKU and the shipped item come from
 * the variant, so an unchecked foreign variant lets a customer buy one
 * product's variant at another product's price.
 *
 * The sibling product field is derived from the attribute name, so the rule
 * works both for a flat `variant_id` and for `items.N.variant_id`.
 */
class VariantBelongsToProduct implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $productKey = preg_replace('/variant_id$/', 'product_id', $attribute);
        $productId = Arr::get($this->data, (string) $productKey);

        $belongs = is_numeric($productId) && is_numeric($value)
            && ProductVariant::query()
                ->whereKey((int) $value)
                ->where('product_id', (int) $productId)
                ->exists();

        if (! $belongs) {
            $fail('The selected variant is invalid for the given product.');
        }
    }
}
