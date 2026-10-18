<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SteadfastCourierService
{
    public function __construct(
        private readonly int $timeout = 0,
    ) {}

    /**
     * Book a Steadfast parcel for an existing ShoppyDrive order.
     *
     * @return array{consignment_id: string, tracking_code: string|null, status: string}
     *
     * @throws RuntimeException
     */
    public function createOrder(Order $order): array
    {
        $address = $order->shipping_address;

        $payload = [
            'invoice' => $this->truncate($order->order_number, 100),
            'recipient_name' => $this->truncate($address['name'] ?? 'Customer', 100),
            'recipient_phone' => $this->truncate($this->normalizePhone($address['phone'] ?? ''), 40),
            'recipient_address' => $this->truncate($this->formatAddress($address), 490),
            'cod_amount' => $order->payment_status->value === 'paid' ? 0 : (int) round($order->total),
        ];

        if ($order->notes) {
            $payload['note'] = $this->truncate($order->notes, 480);
        }

        $response = $this->client()->post('/create_order', $payload);

        $json = $this->handleResponse($response, 'Create order');

        $consignment = $json['consignment'] ?? [];

        return [
            'consignment_id' => (string) ($consignment['consignment_id'] ?? ''),
            'tracking_code' => $consignment['tracking_code'] ?? null,
            'status' => $consignment['status'] ?? 'in_review',
        ];
    }

    /**
     * @return array{status: string}
     *
     * @throws RuntimeException
     */
    public function getOrderInfo(string $consignmentId): array
    {
        $response = $this->client()->get("/status_by_cid/{$consignmentId}");

        $json = $this->handleResponse($response, 'Get order status');

        return [
            'status' => $json['delivery_status'] ?? 'unknown',
        ];
    }

    /**
     * Current balance Steadfast owes the merchant — used by the admin Settings
     * page to confirm the configured Api Key/Secret Key are valid.
     *
     * @return array{current_balance: float}
     *
     * @throws RuntimeException
     */
    public function getBalance(): array
    {
        $response = $this->client()->get('/get_balance');

        $json = $this->handleResponse($response, 'Get balance');

        return [
            'current_balance' => (float) ($json['current_balance'] ?? 0),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->requestTimeout())
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Api-Key' => $this->requireSetting('steadfast_api_key'),
                'Secret-Key' => $this->requireSetting('steadfast_secret_key'),
            ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('steadfast.base_url'), '/');
    }

    private function requestTimeout(): int
    {
        return $this->timeout > 0 ? $this->timeout : (int) config('steadfast.timeout', 30);
    }

    private function requireSetting(string $key): string
    {
        $value = Setting::where('key', $key)->first()?->value;

        if (blank($value)) {
            throw new RuntimeException("Steadfast courier is not fully configured. Missing setting: {$key}.");
        }

        return (string) $value;
    }

    private function handleResponse(Response $response, string $action): array
    {
        $json = $response->json() ?? [];

        if ($response->failed()) {
            Log::error("[Steadfast] {$action} failed", [
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = $this->firstErrorMessage($json) ?? "Steadfast {$action} failed with HTTP {$response->status()}.";

            throw new RuntimeException($message);
        }

        // Steadfast's bulk-order endpoint reports per-item failures inside an
        // otherwise-200 response via a `status` field — check it regardless.
        $status = $json['status'] ?? null;

        if ($status !== null && ! in_array((int) $status, [200, 201], true)) {
            Log::error("[Steadfast] {$action} returned an error payload", ['response' => $json]);

            throw new RuntimeException($this->firstErrorMessage($json) ?? "Steadfast {$action} failed.");
        }

        return $json;
    }

    /** @param  array<string, mixed>  $json */
    private function firstErrorMessage(array $json): ?string
    {
        if (is_string($json['message'] ?? null)) {
            return $json['message'];
        }

        if (is_array($json['errors'] ?? null)) {
            $flattened = Arr::flatten($json['errors']);

            if (! empty($flattened)) {
                return (string) $flattened[0];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $address */
    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['address_line1'] ?? null,
            $address['address_line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
        ]);

        return implode(', ', $parts);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
    }
}
