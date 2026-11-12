<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Rules\StorableDateTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date', new StorableDateTime],
            'ends_at' => ['required', 'date', new StorableDateTime, 'after:starts_at'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            // Null means "the whole product, every variant" — the admin UI
            // offers both that and a specific variant combination whenever
            // the selected product has any (see FlashSaleItem::findActiveFor(),
            // which already prefers an exact variant match over a
            // product-wide one at pricing/stock-enforcement time).
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.sale_price' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
        ];
    }

    /**
     * Two things the plain `rules()` above can't express on their own,
     * since each needs to look at an item together with its siblings or
     * with the database — same convention as `Admin\CreateOrderRequest::after()`:
     * guard on prior errors first, iterate `items` by index, add
     * dotted-path errors.
     *
     * 1. A `variant_id` must actually belong to its own item's
     *    `product_id` — the same mismatched-variant concern
     *    `CreateOrderRequest` and `InventoryService::decrementForOrderItem()`
     *    (FSA-01) both already guard against.
     * 2. The same (product, variant) pair can't appear twice in one
     *    submission — two conflicting prices for the exact same thing
     *    would just mean the last one silently wins.
     *
     * A flash sale has no quantity of its own: it sells from the product's
     * main stock, which `InventoryService` checks at checkout.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $validator->validated()['items'] ?? [];

            if ($items === []) {
                return;
            }

            $products = Product::with('variants')
                ->whereIn('id', array_column($items, 'product_id'))
                ->get()
                ->keyBy('id');

            $seenCombinations = [];

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);

                if ($product === null) {
                    continue;
                }

                $variantId = $item['variant_id'] ?? null;
                $combination = $item['product_id'].':'.($variantId ?? 'whole');

                if (isset($seenCombinations[$combination])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'This product (or variant) was already added above.',
                    );

                    continue;
                }

                $seenCombinations[$combination] = true;

                if ($variantId !== null && $product->variants->firstWhere('id', $variantId) === null) {
                    $validator->errors()->add(
                        "items.{$index}.variant_id",
                        'This variant does not belong to the selected product.',
                    );

                    continue;
                }

                // BIZ-020: a whole-product sale also covers its cheapest variant.
                $regularPrice = $variantId !== null
                    ? (float) $product->variants->firstWhere('id', $variantId)->price
                    : (float) $product->variants->pluck('price')->push($product->price)->filter()->min();

                if ((float) $item['sale_price'] >= $regularPrice) {
                    $validator->errors()->add(
                        "items.{$index}.sale_price",
                        'The sale price must be below the regular price of ৳'.number_format($regularPrice, 2).'.',
                    );
                }
            }
        }];
    }
}
