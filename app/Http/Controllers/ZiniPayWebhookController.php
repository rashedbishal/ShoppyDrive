<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ZiniPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZiniPayWebhookController extends Controller
{
    public function __construct(private readonly ZiniPayService $ziniPay) {}

    /**
     * Handle webhook from ZiniPay (GET with query params or POST with JSON body).
     *
     * ZiniPay sends: ?invoiceId=UUID&invoice_id=UUID&val_id=ORD-...
     * We only need val_id to look up the order, then we verify independently.
     *
     * Route: GET|POST /api/zinipay/webhook  (CSRF-exempt via api middleware group)
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('[ZiniPay] Webhook received', ['payload' => $payload]);

        $valId = $payload['val_id'] ?? null;

        if (! $valId) {
            Log::warning('[ZiniPay] Webhook missing val_id', ['payload' => $payload]);

            return response()->json(['message' => 'Missing val_id'], 400);
        }

        // Find the order using our reference (val_id = order_number)
        $order = Order::where('order_number', $valId)->first();

        if (! $order) {
            Log::error('[ZiniPay] Webhook: order not found', ['val_id' => $valId]);

            // Return 200 so ZiniPay stops retrying for unknown orders
            return response()->json(['message' => 'Order not found'], 200);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('val_id', $valId)
            ->first();

        if (! $payment) {
            Log::error('[ZiniPay] Webhook: payment record not found', ['val_id' => $valId, 'order_id' => $order->id]);

            return response()->json(['message' => 'Payment record not found'], 200);
        }

        // Idempotency: if already paid, acknowledge and stop
        if ($payment->status === PaymentStatus::PAID) {
            Log::info('[ZiniPay] Webhook: already processed (idempotent)', ['val_id' => $valId]);

            return response()->json(['message' => 'Already processed']);
        }

        // The webhook payload carries a "status": "true" flag but we NEVER trust
        // the raw webhook alone — always verify with the ZiniPay API.
        try {
            $verified = $this->ziniPay->verifyPayment($valId);
        } catch (\Throwable $e) {
            Log::error('[ZiniPay] Webhook verification exception', [
                'val_id' => $valId,
                'error' => $e->getMessage(),
            ]);

            // Return 500 so ZiniPay will retry the webhook later
            return response()->json(['message' => 'Verification error, will retry'], 500);
        }

        $status = strtoupper($verified['status'] ?? '');

        if ($status === 'COMPLETED') {
            $this->markPaid($order, $payment, $verified);

            return response()->json(['message' => 'Payment confirmed']);
        }

        if ($status === 'FAILED') {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'gateway_response' => $verified,
            ]);

            Log::warning('[ZiniPay] Webhook: payment FAILED', ['val_id' => $valId]);

            return response()->json(['message' => 'Payment failed recorded']);
        }

        // PENDING or unknown status — acknowledge without changing state
        Log::info('[ZiniPay] Webhook: non-terminal status, no action', [
            'val_id' => $valId,
            'status' => $status,
        ]);

        return response()->json(['message' => 'No action for status: '.$status]);
    }

    /** @param array<string, mixed> $verified */
    private function markPaid(Order $order, Payment $payment, array $verified): void
    {
        DB::transaction(function () use ($order, $payment, $verified): void {
            // Lock payment row to prevent callback + webhook race
            $fresh = Payment::lockForUpdate()->find($payment->id);

            if ($fresh && $fresh->status === PaymentStatus::PAID) {
                return; // Callback already handled it
            }

            $payment->update([
                'status' => PaymentStatus::PAID,
                'transaction_id' => $verified['transaction_id'] ?? null,
                'gateway_response' => $verified,
            ]);

            $order->update([
                'payment_status' => PaymentStatus::PAID,
                'status' => 'processing',
            ]);

            $order->statusHistories()->create([
                'status' => 'processing',
                'notes' => 'Payment confirmed via ZiniPay webhook. Transaction: '.($verified['transaction_id'] ?? 'N/A'),
            ]);
        });

        Log::info('[ZiniPay] Webhook: marked PAID', [
            'order_number' => $order->order_number,
            'transaction_id' => $verified['transaction_id'] ?? null,
        ]);
    }
}
