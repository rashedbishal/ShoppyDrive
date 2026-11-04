<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PendingCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly LoyaltyPointsService $loyalty,
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Create an order from a verified PendingCheckout and mark payment as paid.
     * Uses a DB lock to prevent double-creation from concurrent callback + webhook.
     *
     * @param  array<string, mixed>  $verifiedPayment  ZiniPay verify response
     */
    public function createFromPending(PendingCheckout $pending, array $verifiedPayment): Order
    {
        return DB::transaction(function () use ($pending, $verifiedPayment): Order {
            // The purchaser's lock comes first so the expiry check below can't
            // pass while a concurrent checkout, holding that lock, already
            // treats the reservation as expired and spends the same points.
            $lockedUser = $pending->user_id !== null ? $this->referrals->lockUser($pending->user_id) : null;

            // Lock row — prevent race between callback and webhook
            $fresh = PendingCheckout::lockForUpdate()->find($pending->id);

            if ($fresh === null || $fresh->isExpired()) {
                throw new \RuntimeException('Pending checkout not found or expired.');
            }

            // Already handled by the other path (callback beat webhook or vice versa)
            if ($fresh->order_number !== null) {
                return Order::where('order_number', $fresh->order_number)->firstOrFail();
            }

            $data = $fresh->checkout_data;

            $order = Order::create([
                'order_number' => $data['order_number'],
                'user_id' => $fresh->user_id,
                'status' => 'processing',
                'payment_status' => PaymentStatus::PAID->value,
                'payment_method' => PaymentMethod::ZINIPAY->value,
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
                'notes' => 'Order created after ZiniPay payment confirmed. Transaction: '.($verifiedPayment['transaction_id'] ?? 'N/A'),
            ]);

            foreach ($data['items'] as $item) {
                $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $item['product_id'], $item['variant_id'], $item['quantity'], (float) $item['price']);
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'flash_sale_item_id' => $flashSaleItemId,
                    'coupon_applied' => $item['coupon_applied'] ?? null,
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
                $this->coupons->consume($data['coupon_id'], $order, $fresh->user_id, (float) $data['discount'], $data['shipping_address']['phone'] ?? null);
            }

            // Create payment record
            Payment::create([
                'order_id' => $order->id,
                'method' => PaymentMethod::ZINIPAY->value,
                'amount' => $data['total'],
                'status' => PaymentStatus::PAID,
                'val_id' => $fresh->token,
                'transaction_id' => $verifiedPayment['transaction_id'] ?? null,
                'gateway_response' => $verifiedPayment,
            ]);

            // Mark pending checkout as completed
            $fresh->update(['order_number' => $order->order_number]);

            // Clear the user's cart
            $this->clearCart($fresh);

            // A real order went through for this phone number (or this
            // logged-in customer, who may have used a different phone
            // number than their abandoned attempt), so any abandoned-
            // checkout rows tracked against it no longer apply.
            if (! empty($data['shipping_address']['phone'])) {
                IncompleteOrder::deleteCompletedFor($data['shipping_address']['phone'], $order->user_id);
            }

            Log::info('[OrderService] Order created from PendingCheckout', [
                'order_number' => $order->order_number,
                'token' => $fresh->token,
            ]);

            return $order;
        });
    }

    private function clearCart(PendingCheckout $pending): void
    {
        if ($pending->cart_id) {
            $cart = Cart::find($pending->cart_id);
            if ($cart) {
                $cart->items()->delete();
                $cart->delete();
            }
        }
    }
}
