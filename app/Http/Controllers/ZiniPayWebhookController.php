<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PendingCheckout;
use App\Services\OrderService;
use App\Services\ZiniPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZiniPayWebhookController extends Controller
{
    public function __construct(
        private readonly ZiniPayService $ziniPay,
        private readonly OrderService $orderService,
    ) {}

    /**
     * Handle webhook GET/POST from ZiniPay.
     * ZiniPay sends: ?invoiceId=UUID&invoice_id=UUID&val_id=PENDING_TOKEN
     * Route: GET|POST /api/zinipay/webhook
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('[ZiniPay] Webhook received', ['payload' => $payload]);

        $token = $payload['val_id'] ?? null;

        if (! $token) {
            Log::warning('[ZiniPay] Webhook missing val_id');

            return response()->json(['message' => 'Missing val_id'], 400);
        }

        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending) {
            // Could be already processed and deleted — return 200 to stop retries
            Log::info('[ZiniPay] Webhook: no pending checkout (already processed?)', ['token' => $token]);

            return response()->json(['message' => 'Already processed or unknown']);
        }

        if ($pending->order_number !== null) {
            Log::info('[ZiniPay] Webhook: order already created (idempotent)', ['token' => $token]);

            return response()->json(['message' => 'Already processed']);
        }

        // Always verify independently — never trust raw webhook
        try {
            $verified = $this->ziniPay->verifyPayment($token);
        } catch (\Throwable $e) {
            Log::error('[ZiniPay] Webhook verification exception', ['token' => $token, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Verification error, will retry'], 500);
        }

        $status = strtoupper($verified['status'] ?? '');

        if ($status === 'COMPLETED') {
            try {
                $this->orderService->createFromPending($pending, $verified);
            } catch (\Throwable $e) {
                Log::error('[ZiniPay] Webhook: order creation failed', ['token' => $token, 'error' => $e->getMessage()]);

                return response()->json(['message' => 'Order creation error'], 500);
            }

            return response()->json(['message' => 'Payment confirmed, order created']);
        }

        if ($status === 'FAILED') {
            $pending->delete();
            Log::warning('[ZiniPay] Webhook: payment FAILED, pending checkout removed', ['token' => $token]);

            return response()->json(['message' => 'Payment failed recorded']);
        }

        Log::info('[ZiniPay] Webhook: non-terminal status', ['token' => $token, 'status' => $status]);

        return response()->json(['message' => 'No action for status: '.$status]);
    }
}
