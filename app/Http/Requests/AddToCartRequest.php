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
            // Same visibility as Product::isPubliclyVisible() — including
            // the cascading brand/category/sub-category active check, so a
            // product can't be added while one of those is deactivated.
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNull('deleted_at')
                ->whereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('categories')
                    ->whereColumn('categories.id', 'products.category_id')
                    ->where('categories.is_active', true))
                ->where(fn (Builder $q) => $q->whereNull('sub_category_id')->orWhereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('sub_categories')
                    ->whereColumn('sub_categories.id', 'products.sub_category_id')
                    ->where('sub_categories.is_active', true)))
                ->where(fn (Builder $q) => $q->whereNull('brand_id')->orWhereExists(fn (Builder $sub) => $sub->selectRaw('1')->from('brands')
                    ->whereColumn('brands.id', 'products.brand_id')
                    ->where('brands.is_active', true))))],
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
