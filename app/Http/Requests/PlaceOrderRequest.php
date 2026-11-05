<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Middleware\EnsureCheckoutAccess;
use App\Rules\VariantBelongsToProduct;
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
            // FSA-23: 'zinipay' deliberately removed — ZiniPayService has
            // no initiation path anywhere (only verifyPayment(), called
            // from the historical-only callback/webhook), so a new
            // checkout submitting it must fail validation here instead of
            // silently falling through CheckoutController::completeCheckout()'s
            // COD/else branching into the unrelated mobile-banking flow.
            'payment_method' => ['required', 'in:cod,mobile_banking'],
            // Missing/malformed must fail validation here, never silently
            // skip the idempotency check in CheckoutController::store()
            // (CHECKOUT-COD-002, same requirement as CART-01 on the landing
            // page flow).
            'order_token' => ['required', 'uuid'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id', new VariantBelongsToProduct],
            // BIZ-011: no upper bound let a huge quantity (e.g. 3000000000)
            // reach the DB layer, which 500s on MariaDB's INT overflow
            // instead of failing validation — matches the landing page's
            // existing single-product cap.
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
            'items.*.bundle_id' => ['nullable', 'integer', 'exists:product_bundles,id'],
            'items.*.bundle_unit_quantity' => ['nullable', 'integer', 'min:1'],
            // BIZ-001: what the checkout page displayed for this line, used
            // only by CartManager::submittedPricesStale() to decide whether
            // to refuse before anything is charged — never to decide the
            // actual charge, which always comes from CartManager::resolvePrice().
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
        ];
    }
}
