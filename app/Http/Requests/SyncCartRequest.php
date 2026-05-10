<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SyncCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'merge' => ['sometimes', 'boolean'],
            'items' => ['present', 'array'],
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
