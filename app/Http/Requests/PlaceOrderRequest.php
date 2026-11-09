<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Http\Middleware\EnsureCheckoutAccess;
use App\Rules\VariantBelongsToProduct;
use App\Support\BangladeshDistricts;
use App\Support\SmsForwarderHeartbeat;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    use NormalizesBangladeshiPhone;

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
            // BIZ-011: honeypot. Hidden from real customers, so anything in
            // it means a bot filled every field.
            'sd_confirm_ref' => ['prohibited'],
            'address_id' => $isGuest ? ['prohibited'] : ['nullable', 'integer'],
            'address_line1' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255'],
            'state' => [$usingSaved ? 'nullable' : 'required', 'string', 'max:255', Rule::in(BangladeshDistricts::ALL)],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'save_address' => ['boolean'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'redeem_points' => ['nullable', 'boolean'],
            'points_to_redeem' => ['nullable', 'integer', 'min:1', 'required_if:redeem_points,true'],
            // FSA-23: 'zinipay' deliberately removed. The ZiniPay gateway
            // was retired entirely (BIZ-026: callback/webhook routes and
            // ZiniPayService deleted), so a new
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
            // Existence (and visibility — deactivated, or hidden via its
            // brand/category/sub-category) is deliberately NOT checked
            // here with exists:products,id: a deleted/unavailable product
            // must become one of CheckoutController::collectCartIssues()'s
            // named, customer-facing errors, not an opaque validation
            // failure the frontend has no specific key to watch for.
            'items.*.product_id' => ['required_with:items', 'integer'],
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
            'sd_confirm_ref.prohibited' => 'We could not place this order. Please refresh the page and try again.',
        ];
    }

    /**
     * OPS-005: a dead forwarder phone can never match a bKash/Nagad
     * Transaction ID, so refuse the attempt here rather than let the
     * customer submit one that can never verify. Checked server-side
     * regardless of the `mobile_banking_available` prop the checkout page
     * already hides the option on, in case that page was loaded before
     * the phone went down.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('payment_method') === 'mobile_banking' && SmsForwarderHeartbeat::isDown()) {
                $validator->errors()->add('payment_method', 'Mobile payment verification is temporarily unavailable. Please choose Cash on Delivery instead.');
            }
        });
    }
}
