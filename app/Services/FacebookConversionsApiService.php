<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The single place a Facebook Conversions API event is built and sent — see
 * PathaoCourierService/SteadfastCourierService/AiAssistantService for the
 * same "one service owns one external integration" shape. Never throws: a
 * failed call to Meta's Graph API must never be visible to the customer or
 * affect order creation, so every failure is logged and swallowed. Retries
 * are the caller's job (SendFacebookPurchaseEventJob), not this service's.
 */
class FacebookConversionsApiService
{
    private const GRAPH_API_VERSION = 'v21.0';

    public function sendPurchaseEvent(Order $order): void
    {
        if (! FacebookConversionsApiConfig::isAvailable()) {
            return;
        }

        $payload = $this->buildPurchasePayload($order);

        try {
            $response = Http::asJson()->timeout(15)->post(
                sprintf(
                    'https://graph.facebook.com/%s/%s/events',
                    self::GRAPH_API_VERSION,
                    FacebookConversionsApiConfig::pixelId(),
                ),
                [
                    ...$payload,
                    'access_token' => FacebookConversionsApiConfig::accessToken(),
                ],
            );
        } catch (ConnectionException $e) {
            Log::warning('[Facebook CAPI] Could not reach Graph API', [
                'order_number' => $order->order_number,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('[Facebook CAPI] Purchase event rejected', [
                'order_number' => $order->order_number,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPurchasePayload(Order $order): array
    {
        $event = [
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            'event_id' => "order-{$order->order_number}",
            'action_source' => 'website',
            'event_source_url' => route('order.confirmation', $order->order_number),
            'user_data' => $this->buildUserData($order),
            'custom_data' => $this->buildCustomData($order),
        ];

        $testEventCode = FacebookConversionsApiConfig::testEventCode();

        return [
            'data' => [$event],
            ...($testEventCode !== null ? ['test_event_code' => $testEventCode] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserData(Order $order): array
    {
        $email = $order->user?->email ?: ($order->shipping_address['email'] ?? null);
        $phone = $order->shipping_address['phone'] ?? null;

        $userData = [
            'client_ip_address' => $order->ip_address,
            'client_user_agent' => $order->user_agent,
        ];

        if ($email) {
            $userData['em'] = [$this->hash(strtolower(trim($email)))];
        }

        if ($phone) {
            $userData['ph'] = [$this->hash($this->normalizeBangladeshiPhone($phone))];
        }

        return $userData;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomData(Order $order): array
    {
        return [
            'currency' => 'BDT',
            'value' => $order->total,
            'order_id' => $order->order_number,
            'contents' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->sku ?: (string) $item->id,
                'quantity' => $item->quantity,
                'item_price' => $item->price,
            ])->all(),
        ];
    }

    /**
     * Stored local format is always `01XXXXXXXXX` (PlaceOrderRequest
     * validates `^01[3-9][0-9]{8}$`) — Meta expects digits only, with
     * country code and no leading zero: drop the leading 0, prepend 880.
     */
    private function normalizeBangladeshiPhone(string $phone): string
    {
        return '880'.substr($phone, 1);
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
