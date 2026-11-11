<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesBangladeshiPhone;
use App\Http\Middleware\EnsureCheckoutAccess;
use App\Models\Product;
use App\Rules\VariantBelongsToProduct;
use App\Support\BangladeshDistricts;
use App\Support\SmsForwarderHeartbeat;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    use NormalizesBangladeshiPhone {
        // This class defines its own prepareForValidation(), which would
        // otherwise silently replace the trait's phone clean-up (FE-012).
        prepareForValidation as protected normalizePhoneInput;
    }

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
            // "Buy Now": the order is that one product alone and never touches
            // the customer's cart (CheckoutController::store()).
            'buy_now' => ['nullable', 'boolean'],
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
            // BE-002: a guest cart has no server-side row, so if this
            // product is deleted before the order lands, the server has
            // nothing else to name it with in the cart-issue message —
            // display text only, never read for price/identity/stock.
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A permanently deleted product takes its variant rows with it (and a
     * guest's or stale tab's submission still carries that variant_id), so
     * the variant rules below would fail on a line CheckoutController::store()
     * is about to refuse anyway — as a silent `items.N.variant_id` field
     * error the checkout page never shows. Clearing the variant for a
     * line whose product no longer exists lets that named, customer-facing
     * "no longer available" cart issue through instead; the whole checkout
     * is refused before the variant is ever read, and every line whose
     * product does exist is validated exactly as before.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $productIds = collect($items)
            ->map(fn ($item) => is_array($item) ? ($item['product_id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $existingIds = Product::query()->whereIn('id', $productIds)->pluck('id')->all();

        foreach ($items as $index => $item) {
            if (is_array($item)
                && is_numeric($item['product_id'] ?? null)
                && ! in_array((int) $item['product_id'], $existingIds, true)
            ) {
                $items[$index]['variant_id'] = null;
            }
        }

        $this->merge(['items' => $items]);
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

            // A Buy Now order is exactly one product line (never a bundle),
            // so a crafted request can't use it to slip a whole cart through
            // the path that skips the real cart.
            if ($this->boolean('buy_now')) {
                $items = $this->input('items');

                if (! is_array($items) || count($items) !== 1 || ! empty(data_get($items, '0.bundle_id'))) {
                    $validator->errors()->add('items', 'Buy Now is for one product at a time.');
                }
            }
        });
    }
}
