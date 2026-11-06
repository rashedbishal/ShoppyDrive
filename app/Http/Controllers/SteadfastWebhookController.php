<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CourierEvidenceSource;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CourierOrderStatusMapper;
use App\Support\SafeLogContext;
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

        Log::info('[Steadfast] Webhook received', SafeLogContext::steadfastWebhook($payload));

        if ($event !== 'delivery_status') {
            // Other event types (tracking_update, consignment_update, return_*,
            // payment_request, cancel_request, pickup_request, user_update) are
            // logged above for visibility but don't update an order yet.
            return response()->json(['status' => 'success']);
        }

        $order = $this->findOrder($payload);

        if (! $order) {
            Log::info('[Steadfast] Webhook: no matching order', SafeLogContext::steadfastWebhook($payload));

            return response()->json(['status' => 'success']);
        }

        $newStatus = $payload['status'] ?? null;

        if ($newStatus !== null) {
            CourierOrderStatusMapper::sync($order, 'steadfast', $newStatus, $payload['tracking_message'] ?? null, CourierEvidenceSource::STEADFAST_WEBHOOK);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * COURIER-SEND-RACE-001: match on the exact consignment ID only. An
     * invoice match is never enough — a duplicate consignment booked for the
     * same invoice (and never recorded on the order) would otherwise move
     * the real order's status. No consignment ID, no match, more than one
     * match, or an invoice that contradicts the matched order → the order is
     * left alone (the webhook is still acknowledged). Whether Steadfast always
     * includes `consignment_id` in delivery_status webhooks is unverified.
     *
     * @param  array<string, mixed>  $payload
     */
    private function findOrder(array $payload): ?Order
    {
        $consignmentId = isset($payload['consignment_id']) ? trim((string) $payload['consignment_id']) : '';

        if ($consignmentId === '') {
            Log::warning('[Steadfast] Webhook ignored: no consignment_id to match on.', ['invoice' => $payload['invoice'] ?? null]);

            return null;
        }

        $matches = Order::withTrashed()->where('steadfast_consignment_id', $consignmentId)->limit(2)->get();

        if ($matches->count() !== 1) {
            Log::warning('[Steadfast] Webhook ignored: consignment_id did not match exactly one order.', ['consignment_id' => $consignmentId, 'matches' => $matches->count()]);

            return null;
        }

        $order = $matches->first();

        // Trashed orders are counted above (so they still make a match
        // ambiguous) but, as before, never updated.
        if ($order->trashed()) {
            return null;
        }

        if (! empty($payload['invoice']) && (string) $payload['invoice'] !== $order->order_number) {
            Log::warning('[Steadfast] Webhook ignored: invoice does not match the order holding this consignment.', ['consignment_id' => $consignmentId, 'invoice' => $payload['invoice'], 'order_number' => $order->order_number]);

            return null;
        }

        return $order;
    }

    private function verifySignature(Request $request): bool
    {
        $secret = Setting::where('key', 'steadfast_webhook_secret')->first()?->value;

        // Fail closed, always — an unconfigured secret must never be
        // treated as "verification not required." Until an admin
        // configures a matching secret here and on Steadfast's merchant
        // dashboard, every webhook is rejected rather than silently
        // trusted; this is deliberately not environment-conditional (no
        // "allow unsigned in local/test"), since that would just move the
        // fail-open risk to a config/env flag that could be mis-set in
        // production instead of removing it.
        if (blank($secret)) {
            Log::warning('[Steadfast] Webhook rejected: no webhook secret configured.');

            return false;
        }

        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), (string) $secret);

        return hash_equals($expected, $signature);
    }
}
