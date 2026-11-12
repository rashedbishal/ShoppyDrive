<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\RasterImageOrUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ProductBundleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:300'],
            'image' => ['nullable', new RasterImageOrUrl],
            'discount_type' => ['required', 'string', 'in:fixed_price,free_delivery'],
            'bundle_price' => ['required_if:discount_type,fixed_price', 'nullable', 'numeric', $this->input('discount_type') === 'fixed_price' ? 'gt:0' : 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
            'items' => [
                'required',
                'array',
                'min:2',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $pairs = collect($value)
                        ->map(fn (array $item): string => ($item['product_id'] ?? '').'|'.($item['variant_id'] ?? 'none'));

                    if ($pairs->count() !== $pairs->unique()->count()) {
                        $fail('The same product and variant combination cannot be added to a bundle more than once.');
                    }
                },
            ],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];
                    $productId = (int) $this->input("items.{$index}.product_id");
                    $variantBelongsToProduct = ProductVariant::query()
                        ->whereKey($value)
                        ->where('product_id', $productId)
                        ->exists();

                    if (! $variantBelongsToProduct) {
                        $fail('The selected variant is invalid for the given product.');
                    }
                },
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
                // BIZ-002 (tester retest, 2026-11-09): this is how many units
                // of the component one bundle set needs, with no per-unit
                // quantity picker anywhere a customer could add less — so a
                // recipe asking for more than the product actually has in
                // stock can never be bought at all, not even once. Mirrors
                // InventoryService::assertAvailable()'s own rule that a
                // variant-less selection of a product that has variants has
                // no stock of its own.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $index = (int) explode('.', $attribute)[1];
                    $productId = (int) $this->input("items.{$index}.product_id");
                    $variantId = $this->input("items.{$index}.variant_id");
                    $variantId = $variantId !== null && $variantId !== '' ? (int) $variantId : null;

                    if ($variantId !== null) {
                        $stock = ProductVariant::query()->whereKey($variantId)->value('stock_quantity');
                    } else {
                        $product = Product::query()->find($productId);

                        if ($product === null) {
                            return;
                        }

                        $stock = $product->variants()->exists() ? 0 : $product->stock_quantity;
                    }

                    if ($stock !== null && $value > $stock) {
                        $fail("This bundle needs {$value}, but only {$stock} unit(s) are in stock — a bundle can never require more of a product than its current stock allows.");
                    }
                },
            ],
        ];
    }
}
