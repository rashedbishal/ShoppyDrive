<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CouponUnavailableException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PendingCheckout;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Services\CouponService;
use App\Services\InventoryService;
use App\Services\LoyaltyPointsService;
use App\Services\ReferralService;
use App\Support\OrderConfirmationAccess;
use App\Support\SafeLogContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MobilePaymentController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly LoyaltyPointsService $loyalty,
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Show the payment instruction page.
     * Route: GET /payment/mobile/{token}
     */
    public function show(string $token): Response|RedirectResponse
    {
        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            return redirect()->route('checkout.index')
                ->with('error', 'Payment session expired. Please try again.');
        }

        // FSA-22: a guest-originated pending checkout (user_id null) has no
        // owner identity to compare against — its unguessable token is
        // already the access boundary, the same way guest_token works for
        // order confirmation. Only enforce ownership when the checkout was
        // actually staged under a specific logged-in user; a different
        // auth state at this point (including having since logged in)
        // must not lock the guest out of their own checkout.
        abort_if($pending->user_id !== null && auth()->id() !== $pending->user_id, 403);

        if ($pending->order_number !== null) {
            OrderConfirmationAccess::grant(request(), $pending->order_number);

            return redirect()->route('order.confirmation', $pending->order_number);
        }

        return Inertia::render('checkout/MobilePayment', [
            'token' => $token,
            'amount' => $pending->checkout_data['total'],
            'orderNumber' => $pending->checkout_data['order_number'],
            'holdsPoints' => $pending->points_reserved > 0,
            'bkashNumber' => Setting::find('bkash_number')?->value,
            'nagadNumber' => Setting::find('nagad_number')?->value,
        ]);
    }

    /**
     * Customer submits provider + transaction ID.
     * Route: POST /payment/mobile/{token}
     */
    /**
     * The payment page's "Cancel payment" link: discards the pending
     * checkout (releasing any reserved points) and returns to checkout with
     * the cart intact. Only the customer who staged it can cancel it.
     * BIZ-026: moved here unchanged from the retired ZiniPay PaymentController.
     */
    public function cancel(string $token): RedirectResponse
    {
        $pending = PendingCheckout::where('token', $token)->first();

        if ($pending && auth()->id() === $pending->user_id) {
            Log::info('[MobilePayment] Pending checkout cancelled by customer', ['pending_checkout_id' => $pending->id]);
            $pending->delete();
        }

        return redirect()->route('checkout.index')
            ->with('info', 'Payment cancelled. Your cart is intact — you can try again anytime.');
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'transaction_id' => ['required', 'string', 'min:4', 'max:40'],
        ]);

        $provider = $request->input('provider');
        $transactionId = strtoupper(trim($request->transaction_id));

        $pending = PendingCheckout::where('token', $token)->first();

        // FSA-07: the hard 2-hour window is never extended, globally or on
        // this row — but a customer who genuinely paid in time and only
        // submits the form late must not be bounced to a brand-new
        // checkout. If a received SMS for this exact (provider, TrxID)
        // arrived within this PendingCheckout's own original window, let
        // this one submission through to the normal matching logic below,
        // which still enforces amount, duplicate-use, wrong-provider, etc.
        // exactly as before. An SMS received after the window still fails.
        if (! $pending || ($pending->isExpired() && ! $this->hasProofOfPaymentWithinWindow($pending, $provider, $transactionId))) {
            return redirect()->route('checkout.index')
                ->with('error', 'Payment session expired. Please start a new order.');
        }

        // FSA-22: see show() above — only a logged-in-originated checkout
        // enforces ownership; a guest-originated one is accessible by
        // token alone regardless of the submitter's current auth state.
        abort_if($pending->user_id !== null && auth()->id() !== $pending->user_id, 403);

        if ($pending->order_number !== null) {
            OrderConfirmationAccess::grant($request, $pending->order_number);

            return redirect()->route('order.confirmation', $pending->order_number);
        }

        $expectedAmount = (float) $pending->checkout_data['total'];

        try {
            $order = DB::transaction(function () use ($pending, $provider, $transactionId, $expectedAmount): Order {
                // The purchaser's lock comes first so this expiry check can't
                // pass while a concurrent checkout, holding that lock, already
                // treats the reservation as expired and spends the same points.
                $lockedUser = $pending->user_id !== null ? $this->referrals->lockUser($pending->user_id) : null;
                $freshPending = PendingCheckout::lockForUpdate()->find($pending->id);

                if ($freshPending === null) {
                    throw new \RuntimeException('expired');
                }

                // FSA-07: same grace as the outer check above, re-verified
                // under lock since this is the authoritative check.
                if ($freshPending->isExpired() && ! $this->hasProofOfPaymentWithinWindow($freshPending, $provider, $transactionId)) {
                    throw new \RuntimeException('expired');
                }

                if ($freshPending->order_number !== null) {
                    return Order::where('order_number', $freshPending->order_number)->firstOrFail();
                }

                // Lock the row for this (provider, transaction id) regardless of
                // match status: a unique DB constraint guarantees at most one row
                // exists per real transaction, so the lock serializes concurrent
                // or duplicate-SMS claims against the same transaction and lets
                // us tell "already consumed" apart from "never existed".
                $sms = ReceivedSms::lockForUpdate()
                    ->where('parsed_transaction_id', $transactionId)
                    ->where('provider', $provider)
                    ->first();

                if ($sms && $sms->is_matched) {
                    throw new \RuntimeException('already_used');
                }

                if (! $sms) {
                    // Check if TxnID exists but for a different provider
                    $existsOtherProvider = ReceivedSms::where('parsed_transaction_id', $transactionId)
                        ->where('is_matched', false)
                        ->exists();

                    throw new \RuntimeException($existsOtherProvider ? 'wrong_provider' : 'not_found');
                }

                // BIZ-025: the payment must have reached us after this checkout
                // started (owner: strict). A known but unused TrxID from an
                // earlier payment (e.g. seen in a shared screenshot) can no
                // longer pay for someone else's same-amount order. Compares our
                // own server's receive time (created_at), not the forwarder
                // phone's clock (received_at), so a slow phone clock can't
                // refuse a genuine payment.
                if ($sms->created_at->lt($freshPending->created_at)) {
                    throw new \RuntimeException('before_checkout');
                }

                // A matching transaction ID alone is not proof of payment for
                // the right amount — if the SMS text didn't parse to a usable
                // amount (provider template drift, a forwarder reformatting
                // the message, etc.), that must fail closed, not be treated
                // as "nothing to contradict the claim."
                if ($sms->parsed_amount === null) {
                    throw new \RuntimeException('amount_unavailable');
                }

                if (abs($sms->parsed_amount - $expectedAmount) > 1) {
                    throw new \RuntimeException('amount_mismatch:'.$sms->parsed_amount);
                }

                $data = $freshPending->checkout_data;
                $providerLabel = $provider === 'bkash' ? 'Bkash' : 'Nagad';

                $order = Order::create([
                    'order_number' => $data['order_number'],
                    'user_id' => $freshPending->user_id,
                    'status' => 'processing',
                    'payment_status' => PaymentStatus::PAID->value,
                    'payment_method' => PaymentMethod::MobileBanking->value,
                    'subtotal' => $data['subtotal'],
                    'shipping_cost' => $data['shipping_cost'],
                    'discount' => $data['discount'],
                    'coupon_id' => $data['coupon_id'],
                    'coupon_code' => $data['coupon_code'],
                    'used_referral_discount' => $data['used_referral_discount'] ?? false,
                    'points_redeemed' => ($data['points_redeemed'] ?? 0) ?: null,
                    'tax' => $data['tax'],
                    'total' => $data['total'],
                    'shipping_address' => $data['shipping_address'],
                    'billing_address' => $data['shipping_address'],
                ]);

                // Mirror CheckoutController::store()'s COD side effects: commit
                // whichever referral/points outcome was already baked into
                // checkout_data at checkout time.
                if ($lockedUser !== null) {
                    if ($order->used_referral_discount) {
                        $this->referrals->markReferralDiscountUsed($order, $lockedUser);
                    }

                    if (! empty($data['points_redeemed'])) {
                        $this->loyalty->debitPointsForOrder($order, $lockedUser, (int) $data['points_redeemed'], (float) ($data['points_discount_amount'] ?? 0));
                    }
                }

                $order->statusHistories()->create([
                    'status' => 'processing',
                    'notes' => "Payment confirmed via {$providerLabel}. TrxID: {$transactionId}",
                ]);

                foreach ($data['items'] as $item) {
                    $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $item['product_id'], $item['variant_id'], $item['quantity'], (float) $item['price']);
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'],
                        'flash_sale_item_id' => $flashSaleItemId,
                        'coupon_applied' => $item['coupon_applied'] ?? null,
                        // FSA-12: the snapshot's server-reconciled bundle
                        // tag (CartManager::revalidatePrices()), as COD and
                        // OrderService already persist it.
                        'bundle_id' => $item['bundle_id'] ?? null,
                        'product_name' => $item['product_name'],
                        'sku' => $item['sku'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                        'product_snapshot' => $item['product_snapshot'],
                    ]);
                }

                if ($data['coupon_id'] !== null) {
                    $this->coupons->consume($data['coupon_id'], $order, $freshPending->user_id, (float) $data['discount'], $data['shipping_address']['phone'] ?? null);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method' => PaymentMethod::MobileBanking->value,
                    'amount' => $data['total'],
                    'status' => PaymentStatus::PAID,
                    'transaction_id' => $transactionId,
                    'gateway_response' => [
                        'provider' => $provider,
                        'sms_id' => $sms->id,
                        'sender' => $sms->sender,
                        'parsed_amount' => $sms->parsed_amount,
                    ],
                ]);

                $sms->update(['is_matched' => true, 'matched_order_id' => $order->id]);
                $freshPending->update(['order_number' => $order->order_number]);

                // A real order went through for this phone number (or this
                // logged-in customer, who may have used a different phone
                // number than their abandoned attempt), so any abandoned-
                // checkout rows tracked against it no longer apply.
                if (! empty($data['shipping_address']['phone'])) {
                    IncompleteOrder::deleteCompletedFor($data['shipping_address']['phone'], $order->user_id);
                }

                if ($freshPending->cart_id) {
                    $cart = Cart::find($freshPending->cart_id);
                    if ($cart) {
                        $this->removePaidLines($cart, $data['items']);
                    }
                }

                Log::info('[MobilePayment] Order confirmed', [
                    'order_number' => $order->order_number,
                    'provider' => $provider,
                    'transaction_id' => $transactionId,
                ]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $providerLabel = $provider === 'bkash' ? 'Bkash' : 'Nagad';

            return match (true) {
                $e instanceof InsufficientStockException => back()->withErrors([
                    'transaction_id' => $msg,
                ]),

                $e instanceof CouponUnavailableException => back()->withErrors([
                    'transaction_id' => $msg,
                ]),

                $msg === 'expired' => redirect()->route('checkout.index')
                    ->with('error', 'Payment session expired.'),

                // OPS-005: this can mean the ID was mistyped, or that the
                // SMS simply hasn't reached us yet (forwarding can lag or
                // occasionally miss) — the app has no way to tell which,
                // so the message no longer flatly implies the customer is
                // wrong.
                $msg === 'not_found' => back()->withErrors([
                    'transaction_id' => "We haven't received this Transaction ID yet. Please double-check it from your {$providerLabel} SMS. If you paid recently, this can take a few minutes to arrive — wait a moment and try again, or contact support if it still doesn't go through.",
                ]),

                $msg === 'wrong_provider' => back()->withErrors([
                    'transaction_id' => 'This Transaction ID belongs to a different payment provider. Please select the correct one.',
                ]),

                // BIZ-025: one generic answer for "used" and "too old", so the
                // page can't be used to learn whether an ID exists or was used.
                $msg === 'already_used', $msg === 'before_checkout' => back()->withErrors([
                    'transaction_id' => "This Transaction ID can't be used for this order. If you paid for this order, please contact support with your Transaction ID.",
                ]),

                $msg === 'amount_unavailable' => back()->withErrors([
                    'transaction_id' => "We couldn't automatically verify the amount for this transaction. Please contact support with your Transaction ID so we can confirm it manually.",
                ]),

                str_starts_with($msg, 'amount_mismatch:') => back()->withErrors([
                    // BIZ-025: never reveal the other payment's amount.
                    'transaction_id' => 'The amount of this payment does not match your order total of ৳'.$expectedAmount.'. Please check the Transaction ID or contact support.',
                ]),

                // SEC-023: this catch also receives database errors, whose
                // message carries SQL values such as the delivery address.
                default => (function () use ($e) {
                    Log::error('[MobilePayment] Unexpected error', SafeLogContext::exception($e));

                    return back()->with('error', 'Something went wrong. Please contact support.');
                })(),
            };
        }

        OrderConfirmationAccess::grant($request, $order->order_number);

        return redirect()->route('order.confirmation', $order->order_number);
    }

    /**
     * FSA-07: whether a received SMS matching this (provider, TrxID) proves
     * payment arrived while $pending was still genuinely open — not merely
     * before "now". This never changes $pending->expires_at itself, and
     * doesn't require the SMS to be unmatched: a matched one still reaches
     * (and is correctly refused by) the normal "already_used" check below,
     * which is a more honest answer than a generic "session expired".
     */
    private function hasProofOfPaymentWithinWindow(PendingCheckout $pending, string $provider, string $transactionId): bool
    {
        return ReceivedSms::where('provider', $provider)
            ->where('parsed_transaction_id', $transactionId)
            ->whereBetween('received_at', [$pending->created_at, $pending->expires_at])
            ->exists();
    }

    /**
     * BIZ-019: the customer may have kept shopping while the payment was
     * pending, so only what this order paid for leaves the cart.
     *
     * @param  list<array{product_id: int, variant_id: int|null, bundle_id?: int|null, quantity: int}>  $paidItems
     */
    private function removePaidLines(Cart $cart, array $paidItems): void
    {
        foreach ($paidItems as $paidItem) {
            $line = $cart->items()
                ->where('product_id', $paidItem['product_id'])
                ->where('variant_id', $paidItem['variant_id'])
                ->where('bundle_id', $paidItem['bundle_id'] ?? null)
                ->first();

            if ($line === null) {
                continue;
            }

            if ($line->quantity > $paidItem['quantity']) {
                $line->decrement('quantity', $paidItem['quantity']);
            } else {
                $line->delete();
            }
        }

        if ($cart->items()->doesntExist()) {
            $cart->delete();
        }
    }
}
