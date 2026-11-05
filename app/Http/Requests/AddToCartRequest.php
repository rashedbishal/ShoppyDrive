<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\VariantBelongsToProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // FSA-05: same visibility as Product::isPubliclyVisible().
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNull('deleted_at'))],
            'variant_id' => ['nullable', 'exists:product_variants,id', new VariantBelongsToProduct],
            // BIZ-011: matches PlaceOrderRequest's cap — an unbounded
            // quantity reaches the DB layer and 500s on MariaDB's INT
            // overflow instead of failing validation here.
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'This product is not available.',
        ];
    }
}
