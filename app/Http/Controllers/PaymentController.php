<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\CouponUnavailableException;
use App\Exceptions\InsufficientStockException;
use App\Models\PendingCheckout;
use App\Services\OrderService;
use App\Services\ZiniPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ZiniPayService $ziniPay,
        private readonly OrderService $orderService,
    ) {}

    /**
     * ZiniPay redirects here after payment attempt.
     * Route: GET /payment/callback/{token}
     */
    public function callback(string $token): RedirectResponse|Response
    {
        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            Log::warning('[ZiniPay] Callback: pending checkout not found or expired', ['token' => $token]);

            return redirect()->route('checkout.index')
                ->with('error', 'Payment session expired. Please try again.');
        }

        // Ensure only the owner can access
        abort_if(auth()->id() !== $pending->user_id, 403);

        Log::info('[ZiniPay] Callback received', ['token' => $token]);

        // Webhook may have already created the order
        if ($pending->order_number !== null) {
            $orderNumber = $pending->order_number;
            $pending->delete();

            return redirect()->route('order.confirmation', $orderNumber);
        }

        try {
            $verified = $this->ziniPay->verifyPayment($token);
        } catch (\Throwable $e) {
            Log::error('[ZiniPay] Verification exception on callback', ['token' => $token, 'error' => $e->getMessage()]);

            return redirect()->route('checkout.index')
                ->with('error', 'Payment verification failed. If money was deducted, contact support with reference: '.$token);
        }

        $status = strtoupper($verified['status'] ?? '');

        if ($status === 'COMPLETED') {
            try {
                $order = $this->orderService->createFromPending($pending, $verified);
            } catch (InsufficientStockException $e) {
                // Payment already succeeded on ZiniPay's side but stock can't
                // cover it — keep the PendingCheckout (don't delete) so an
                // admin can resolve/refund manually instead of silently
                // losing the paid-for order.
                Log::error('[ZiniPay] Order creation failed after payment: insufficient stock', [
                    'token' => $token, 'error' => $e->getMessage(),
                ]);

                return redirect()->route('checkout.index')
                    ->with('error', 'Your payment was received, but we hit a stock issue finalizing your order. Please contact support with reference: '.$token);
            } catch (CouponUnavailableException $e) {
                Log::error('[ZiniPay] Order creation failed after payment: coupon no longer valid', [
                    'token' => $token, 'error' => $e->getMessage(),
                ]);

                return redirect()->route('checkout.index')
                    ->with('error', 'Your payment was received, but we hit an issue finalizing your order. Please contact support with reference: '.$token);
            }

            $pending->delete();

            return redirect()->route('order.confirmation', $order->order_number);
        }

        if ($status === 'PENDING') {
            return Inertia::render('checkout/PaymentPending', [
                'reference' => $token,
            ]);
        }

        // FAILED or unknown
        Log::warning('[ZiniPay] Payment not completed on callback', ['token' => $token, 'status' => $status]);
        $pending->delete();

        return redirect()->route('checkout.index')
            ->with('error', 'Payment was not completed. Your cart has been restored — please try again.');
    }

    /**
     * User cancels on ZiniPay's page.
     * Route: GET /payment/cancel/{token}
     */
    public function cancel(string $token): RedirectResponse
    {
        $pending = PendingCheckout::where('token', $token)->first();

        if ($pending && auth()->id() === $pending->user_id) {
            Log::info('[ZiniPay] Payment cancelled by user', ['token' => $token]);
            $pending->delete();
        }

        return redirect()->route('checkout.index')
            ->with('info', 'Payment cancelled. Your cart is intact — you can try again anytime.');
    }
}
