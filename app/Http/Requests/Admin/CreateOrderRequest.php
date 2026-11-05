<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Models\ProductVariant;
use App\Services\ManualOrderPricing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    use NormalizesBangladeshiPhone;

    /** @var array{prices: array<int, float>, subtotal: float, shipping_cost: float, discount: float}|null */
    private ?array $calculatedPricing = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', 'in:cod,mobile_banking'],
            'shipping_cost' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'shipping_override_reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'discount_override_reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'items.*.price_override_reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * Staff may deviate from the calculated pricing, but only with a reason.
     * The comparison always uses server-side prices, so nothing the client
     * sends can claim a price is the catalog price.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $input = $validator->validated();

            foreach ($input['items'] as $index => $item) {
                if (! empty($item['variant_id']) && ProductVariant::whereKey($item['variant_id'])->value('product_id') !== (int) $item['product_id']) {
                    $validator->errors()->add("items.{$index}.variant_id", 'This variant does not belong to the selected product.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $calculated = app(ManualOrderPricing::class)->calculate($input['items'], $input['state'], $input['city']);
            $this->calculatedPricing = $calculated;

            foreach ($input['items'] as $index => $item) {
                if (ManualOrderPricing::differs((float) $item['price'], $calculated['prices'][$index]) && empty($item['price_override_reason'])) {
                    $validator->errors()->add("items.{$index}.price_override_reason", 'Give a reason for changing the calculated price.');
                }
            }

            if (ManualOrderPricing::differs((float) $input['shipping_cost'], $calculated['shipping_cost']) && empty($input['shipping_override_reason'])) {
                $validator->errors()->add('shipping_override_reason', 'Give a reason for changing the calculated shipping cost.');
            }

            if (ManualOrderPricing::differs((float) ($input['discount'] ?? 0), $calculated['discount']) && empty($input['discount_override_reason'])) {
                $validator->errors()->add('discount_override_reason', 'Give a reason for adding a discount.');
            }
        }];
    }

    /**
     * @return array{prices: array<int, float>, subtotal: float, shipping_cost: float, discount: float}
     */
    public function calculatedPricing(): array
    {
        return $this->calculatedPricing;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
            'items.required' => 'Add at least one product to the order.',
        ];
    }
}
