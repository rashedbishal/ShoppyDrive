<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\CourierOrderStatusMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SteadfastWebhookController extends Controller
{
    /**
     * Handle a webhook POST from Steadfast.
     * Route: POST /api/steadfast/webhook
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('[Steadfast] Webhook signature mismatch');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $event = $payload['notification_type'] ?? null;

        Log::info('[Steadfast] Webhook received', ['event' => $event, 'payload' => $payload]);

        if ($event !== 'delivery_status') {
            // Other event types (tracking_update, consignment_update, return_*,
            // payment_request, cancel_request, pickup_request, user_update) are
            // logged above for visibility but don't update an order yet.
            return response()->json(['status' => 'success']);
        }

        $order = $this->findOrder($payload);

        if (! $order) {
            Log::info('[Steadfast] Webhook: no matching order', ['payload' => $payload]);

            return response()->json(['status' => 'success']);
        }

        $newStatus = $payload['status'] ?? null;

        if ($newStatus !== null) {
            CourierOrderStatusMapper::sync($order, 'steadfast', $newStatus, $payload['tracking_message'] ?? null);
        }

        return response()->json(['status' => 'success']);
    }

    /** @param  array<string, mixed>  $payload */
    private function findOrder(array $payload): ?Order
    {
        if (! empty($payload['invoice'])) {
            $order = Order::where('order_number', $payload['invoice'])->first();

            if ($order) {
                return $order;
            }
        }

        if (! empty($payload['consignment_id'])) {
            return Order::where('steadfast_consignment_id', (string) $payload['consignment_id'])->first();
        }

        return null;
    }

    private function verifySignature(Request $request): bool
    {
        $secret = Setting::where('key', 'steadfast_webhook_secret')->first()?->value;

        // No secret configured — the auth token is optional per Steadfast's
        // docs, so accept the request but note it was unverified.
        if (blank($secret)) {
            Log::warning('[Steadfast] Webhook received without a configured webhook secret; signature not verified');

            return true;
        }

        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), (string) $secret);

        return hash_equals($expected, $signature);
    }
}
