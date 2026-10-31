<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Middleware\EnsureCheckoutAccess;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usingSaved = $this->filled('address_id');
        $isGuest = $this->user() === null;

        // Only a guest checking out while "Allow Guest Checkout" is on
        // skips the email requirement — phone stays mandatory either way,
        // and a guest can never have a saved address to look up by id.
        $guestCheckoutRelaxed = $isGuest && EnsureCheckoutAccess::guestCheckoutEnabled();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [$guestCheckoutRelaxed ? 'nullable' : 'required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'address_id' => $isGuest ? ['prohibited'] : ['nullable', 'integer'],
            'address_line1' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255'],
            'state' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'save_address' => ['boolean'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'redeem_points' => ['nullable', 'boolean'],
            'points_to_redeem' => ['nullable', 'integer', 'min:1', 'required_if:redeem_points,true'],
            'payment_method' => ['required', 'in:cod,zinipay,mobile_banking'],
            // Missing/malformed must fail validation here, never silently
            // skip the idempotency check in CheckoutController::store()
            // (CHECKOUT-COD-002, same requirement as CART-01 on the landing
            // page flow).
            'order_token' => ['required', 'uuid'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.bundle_id' => ['nullable', 'integer', 'exists:product_bundles,id'],
            'items.*.bundle_unit_quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
        ];
    }
}
