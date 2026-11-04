<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Product;
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
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            // Null means "the whole product, every variant" — the admin UI
            // offers both that and a specific variant combination whenever
            // the selected product has any (see FlashSaleItem::findActiveFor(),
            // which already prefers an exact variant match over a
            // product-wide one at pricing/stock-enforcement time).
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.sale_price' => ['required', 'numeric', 'min:0'],
            'items.*.stock_limit' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Three things the plain `rules()` above can't express on their own,
     * since each needs to look at an item together with its siblings or
     * with the database — same convention as `Admin\CreateOrderRequest::after()`:
     * guard on prior errors first, iterate `items` by index, add
     * dotted-path errors.
     *
     * 1. A `variant_id` must actually belong to its own item's
     *    `product_id` — the same mismatched-variant concern
     *    `CreateOrderRequest` and `InventoryService::decrementForOrderItem()`
     *    (FSA-01) both already guard against.
     * 2. `stock_limit` must never promise more units than are really
     *    available — the product's total stock for a whole-product item,
     *    or that one variant's own stock for a variant-specific item.
     *    `InventoryService` always checks real stock before this cap
     *    regardless, so a `stock_limit` above it could never actually be
     *    reached — it just silently misrepresents how many units are
     *    really available at the sale price. This is a one-time snapshot
     *    check: if stock later drops below an already-saved `stock_limit`,
     *    this doesn't retroactively catch that — real stock is still
     *    checked, and still protects against overselling, at checkout.
     * 3. The same (product, variant) pair can't appear twice in one
     *    submission — two conflicting prices/limits for the exact same
     *    thing would just mean the last one silently wins.
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

            // Eager-loads variants so `available_stock` reflects the live
            // variant sum (same convention every storefront query uses),
            // not just the denormalized `stock_quantity` column, and so
            // each item's own variant can be matched without a query per
            // row — one query for every referenced product, not one per
            // item.
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

                $availableStock = $product->available_stock;
                $subject = "\"{$product->name}\"";

                if ($variantId !== null) {
                    $variant = $product->variants->firstWhere('id', $variantId);

                    if ($variant === null) {
                        $validator->errors()->add(
                            "items.{$index}.variant_id",
                            'This variant does not belong to the selected product.',
                        );

                        continue;
                    }

                    $availableStock = $variant->stock_quantity;
                    $subject = 'this variant';
                }

                if ((int) $item['stock_limit'] > $availableStock) {
                    $validator->errors()->add(
                        "items.{$index}.stock_limit",
                        "Stock limit can't exceed {$subject}'s current stock ({$availableStock}).",
                    );
                }
            }
        }];
    }
}
