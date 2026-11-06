<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the manual-order and incomplete-order-conversion price previews
 * (BIZ-017), so both preview exactly what ManualOrderPricing will charge.
 * Route middleware does the permission check for each screen.
 */
class OrderPricingPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id', new VariantBelongsToProduct],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
