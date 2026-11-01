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

        abort_if(auth()->id() !== $pending->user_id, 403);

        if ($pending->order_number !== null) {
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
    public function submit(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'transaction_id' => ['required', 'string', 'min:4', 'max:40'],
        ]);

        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            return redirect()->route('checkout.index')
                ->with('error', 'Payment session expired. Please start a new order.');
        }

        abort_if(auth()->id() !== $pending->user_id, 403);

        if ($pending->order_number !== null) {
            return redirect()->route('order.confirmation', $pending->order_number);
        }

        $provider = $request->input('provider');
        $transactionId = strtoupper(trim($request->transaction_id));
        $expectedAmount = (float) $pending->checkout_data['total'];

        try {
            $order = DB::transaction(function () use ($pending, $provider, $transactionId, $expectedAmount): Order {
                // The purchaser's lock comes first so this expiry check can't
                // pass while a concurrent checkout, holding that lock, already
                // treats the reservation as expired and spends the same points.
                $lockedUser = $pending->user_id !== null ? $this->referrals->lockUser($pending->user_id) : null;
                $freshPending = PendingCheckout::lockForUpdate()->find($pending->id);

                if ($freshPending === null || $freshPending->isExpired()) {
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
                        $cart->items()->delete();
                        $cart->delete();
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

                $msg === 'not_found' => back()->withErrors([
                    'transaction_id' => "Transaction ID not found in our {$providerLabel} records. Please double-check the ID from your SMS and try again.",
                ]),

                $msg === 'wrong_provider' => back()->withErrors([
                    'transaction_id' => 'This Transaction ID belongs to a different payment provider. Please select the correct one.',
                ]),

                $msg === 'already_used' => back()->withErrors([
                    'transaction_id' => 'This Transaction ID has already been used to pay for another order.',
                ]),

                $msg === 'amount_unavailable' => back()->withErrors([
                    'transaction_id' => "We couldn't automatically verify the amount for this transaction. Please contact support with your Transaction ID so we can confirm it manually.",
                ]),

                str_starts_with($msg, 'amount_mismatch:') => back()->withErrors([
                    'transaction_id' => 'Amount mismatch. Expected ৳'.$expectedAmount.' but the SMS shows ৳'.substr($msg, 16).'.',
                ]),

                default => (function () use ($msg) {
                    Log::error('[MobilePayment] Unexpected error', ['error' => $msg]);

                    return back()->with('error', 'Something went wrong. Please contact support.');
                })(),
            };
        }

        return redirect()->route('order.confirmation', $order->order_number);
    }
}
