<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', new RasterImageOrUrl],
            'discount_type' => ['required', 'string', 'in:fixed_price,free_delivery'],
            'bundle_price' => ['required_if:discount_type,fixed_price', 'nullable', 'numeric', $this->input('discount_type') === 'fixed_price' ? 'gt:0' : 'min:0'],
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
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
