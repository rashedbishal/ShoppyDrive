<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ZiniPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly ZiniPayService $ziniPay) {}

    /**
     * ZiniPay redirects the customer here after the payment attempt.
     * Route: GET /payment/callback/{order:order_number}
     */
    public function callback(Order $order): RedirectResponse|Response
    {
        // Ensure this order belongs to the authenticated user
        abort_if(auth()->id() !== $order->user_id, 403);

        Log::info('[ZiniPay] Callback received', ['order_number' => $order->order_number]);

        $payment = Payment::where('order_id', $order->id)
            ->where('val_id', $order->order_number)
            ->first();

        // If no payment record exists something went very wrong
        if (! $payment) {
            Log::error('[ZiniPay] No payment record found on callback', ['order_number' => $order->order_number]);

            return redirect()->route('payment.failed', $order->order_number)
                ->with('error', 'Payment record not found. Please contact support.');
        }

        // Idempotent: already confirmed by webhook — just send to confirmation
        if ($payment->status === PaymentStatus::PAID) {
            return redirect()->route('order.confirmation', $order->order_number);
        }

        // Verify with ZiniPay backend (never trust the redirect alone)
        try {
            $verified = $this->ziniPay->verifyPayment($order->order_number);
        } catch (\Throwable $e) {
            Log::error('[ZiniPay] Verification exception on callback', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('payment.failed', $order->order_number)
                ->with('error', 'Payment verification failed. If money was deducted, please contact support with your order number.');
        }

        $status = strtoupper($verified['status'] ?? '');

        if ($status === 'COMPLETED') {
            $this->markPaid($order, $payment, $verified);

            return redirect()->route('order.confirmation', $order->order_number);
        }

        if ($status === 'PENDING') {
            // Payment is still processing — show the confirmation but with a
            // "pending" note. Webhook will update when it settles.
            return Inertia::render('checkout/PaymentPending', [
                'order_number' => $order->order_number,
            ]);
        }

        // FAILED or anything else
        $this->markFailed($order, $payment, $verified);

        return redirect()->route('payment.failed', $order->order_number);
    }

    /**
     * ZiniPay redirects here when the customer cancels on the payment page.
     * Route: GET /payment/cancel/{order:order_number}
     */
    public function cancel(Order $order): RedirectResponse
    {
        abort_if(auth()->id() !== $order->user_id, 403);

        Log::info('[ZiniPay] Payment cancelled by user', ['order_number' => $order->order_number]);

        Payment::where('order_id', $order->id)
            ->where('val_id', $order->order_number)
            ->where('status', PaymentStatus::PENDING)
            ->update(['status' => PaymentStatus::FAILED]);

        return redirect()->route('payment.failed', $order->order_number)
            ->with('info', 'You cancelled the payment. Your order is saved — you can retry payment from your order page.');
    }

    /**
     * Show the payment-failed page.
     * Route: GET /payment/failed/{order:order_number}
     */
    public function failed(Order $order): Response
    {
        abort_if(auth()->id() !== $order->user_id, 403);

        return Inertia::render('checkout/PaymentFailed', [
            'order' => $order->only(['order_number', 'total', 'payment_method']),
        ]);
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    /** @param array<string, mixed> $verified */
    private function markPaid(Order $order, Payment $payment, array $verified): void
    {
        DB::transaction(function () use ($order, $payment, $verified): void {
            // Guard against a race with the webhook
            $fresh = $payment->lockForUpdate()->find($payment->id);

            if ($fresh && $fresh->status === PaymentStatus::PAID) {
                return; // Already handled
            }

            $payment->update([
                'status' => PaymentStatus::PAID,
                'transaction_id' => $verified['transaction_id'] ?? null,
                'gateway_response' => $verified,
            ]);

            $order->update(['payment_status' => PaymentStatus::PAID, 'status' => 'processing']);

            $order->statusHistories()->create([
                'status' => 'processing',
                'notes' => 'Payment confirmed via ZiniPay. Transaction: '.($verified['transaction_id'] ?? 'N/A'),
            ]);
        });

        Log::info('[ZiniPay] Payment marked PAID', [
            'order_number' => $order->order_number,
            'transaction_id' => $verified['transaction_id'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $verified */
    private function markFailed(Order $order, Payment $payment, array $verified): void
    {
        $payment->update([
            'status' => PaymentStatus::FAILED,
            'gateway_response' => $verified,
        ]);

        Log::warning('[ZiniPay] Payment marked FAILED', ['order_number' => $order->order_number]);
    }
}
