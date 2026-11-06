<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CourierSubmissionException;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PathaoCourierService
{
    private const SETTINGS_KEYS = [
        'pathao_environment',
        'pathao_client_id',
        'pathao_client_secret',
        'pathao_username',
        'pathao_password',
        'pathao_store_id',
    ];

    private const TOKEN_SETTINGS_KEY = 'pathao_token';

    public function __construct(
        private readonly int $timeout = 0,
    ) {}

    /**
     * Create a Pathao order for an existing ShoppyDrive order.
     *
     * Everything that can fail before the create request is sent (settings,
     * the access-token request) is a definite "not sent" failure; failures of
     * the create request itself are classified by whether Pathao may have
     * created the order anyway (see CourierSubmissionException).
     *
     * @return array{consignment_id: string, merchant_order_id: string|null, order_status: string, delivery_fee: float}
     *
     * @throws CourierSubmissionException
     */
    public function createOrder(Order $order): array
    {
        try {
            $order->loadMissing('items.product:id,weight');

            $address = $order->shipping_address;
            $storeId = $this->requireSetting('pathao_store_id');

            $payload = [
                'store_id' => (int) $storeId,
                'merchant_order_id' => $order->order_number,
                'recipient_name' => $address['name'] ?? 'Customer',
                'recipient_phone' => $this->normalizePhone($address['phone'] ?? ''),
                'recipient_address' => $this->formatAddress($address),
                'delivery_type' => config('pathao.delivery_type_normal'),
                'item_type' => config('pathao.item_type_parcel'),
                'special_instruction' => $order->notes,
                'item_quantity' => max(1, (int) $order->items->sum('quantity')),
                'item_weight' => $this->estimateWeight($order),
                'item_description' => $this->describeItems($order),
                'amount_to_collect' => $order->payment_status->value === 'paid' ? 0 : (int) round($order->total),
            ];

            $client = $this->client();
        } catch (Throwable $e) {
            throw CourierSubmissionException::notSent($e->getMessage(), $e);
        }

        try {
            $response = $client->post('/aladdin/api/v1/orders', $payload);
        } catch (ConnectionException $e) {
            throw CourierSubmissionException::fromConnectionException('Pathao', $e);
        }

        if ($response->failed()) {
            Log::error('[Pathao] Create order failed', [
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = $response->json('message');

            throw CourierSubmissionException::fromHttpError($response, is_string($message) ? $message : "Pathao Create order failed with HTTP {$response->status()}.");
        }

        $json = $response->json() ?? [];

        if (($json['type'] ?? 'success') !== 'success') {
            Log::error('[Pathao] Create order returned an error payload', ['response' => $json]);

            throw CourierSubmissionException::unknown('error_payload', $json['message'] ?? 'Pathao Create order failed.', $response->status());
        }

        $data = $json['data'] ?? [];
        $consignmentId = (string) ($data['consignment_id'] ?? '');

        if ($consignmentId === '') {
            throw CourierSubmissionException::unknown('missing_consignment_id', 'Pathao reported success without a consignment ID.', $response->status());
        }

        return [
            'consignment_id' => $consignmentId,
            'merchant_order_id' => $data['merchant_order_id'] ?? null,
            'order_status' => $data['order_status'] ?? 'Pending',
            'delivery_fee' => (float) ($data['delivery_fee'] ?? 0),
        ];
    }

    /**
     * @return array{order_status: string, order_status_slug: string, updated_at: string|null}
     *
     * @throws RuntimeException
     */
    public function getOrderInfo(string $consignmentId): array
    {
        $response = $this->client()->get("/aladdin/api/v1/orders/{$consignmentId}/info");

        $json = $this->handleResponse($response, 'Get order info');

        $data = $json['data'] ?? [];

        return [
            'order_status' => $data['order_status'] ?? $data['order_status_slug'] ?? 'Unknown',
            'order_status_slug' => $data['order_status_slug'] ?? 'unknown',
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<int, array{city_id: int, city_name: string}>
     */
    public function getCities(): array
    {
        $response = $this->client()->get('/aladdin/api/v1/city-list');

        return $this->handleResponse($response, 'Get cities')['data']['data'] ?? [];
    }

    /**
     * @return array<int, array{zone_id: int, zone_name: string}>
     */
    public function getZones(int $cityId): array
    {
        $response = $this->client()->get("/aladdin/api/v1/cities/{$cityId}/zone-list");

        return $this->handleResponse($response, 'Get zones')['data']['data'] ?? [];
    }

    /**
     * @return array<int, array{area_id: int, area_name: string, home_delivery_available: bool, pickup_available: bool}>
     */
    public function getAreas(int $zoneId): array
    {
        $response = $this->client()->get("/aladdin/api/v1/zones/{$zoneId}/area-list");

        return $this->handleResponse($response, 'Get areas')['data']['data'] ?? [];
    }

    /**
     * @return array<int, array{store_id: int, store_name: string, store_address: string, is_active: int, city_id: int, zone_id: int, hub_id: int}>
     */
    public function getStores(): array
    {
        $response = $this->client()->get('/aladdin/api/v1/stores');

        return $this->handleResponse($response, 'Get stores')['data']['data'] ?? [];
    }

    /**
     * @param  array{name: string, contact_name: string, contact_number: string, address: string, city_id: int, zone_id: int, area_id: int, secondary_contact?: string, otp_number?: string}  $store
     * @return array{store_name: string}
     */
    public function createStore(array $store): array
    {
        $response = $this->client()->post('/aladdin/api/v1/stores', $store);

        return $this->handleResponse($response, 'Create store')['data'] ?? [];
    }

    /**
     * @return array{price: float, discount: float, promo_discount: float, plan_id: int, cod_enabled: int, cod_percentage: float, additional_charge: float, final_price: float}
     */
    public function pricePlan(float $itemWeight, int $recipientCity, int $recipientZone): array
    {
        $response = $this->client()->post('/aladdin/api/v1/merchant/price-plan', [
            'store_id' => (int) $this->requireSetting('pathao_store_id'),
            'item_type' => config('pathao.item_type_parcel'),
            'delivery_type' => config('pathao.delivery_type_normal'),
            'item_weight' => $itemWeight,
            'recipient_city' => $recipientCity,
            'recipient_zone' => $recipientZone,
        ]);

        return $this->handleResponse($response, 'Price plan')['data'] ?? [];
    }

    /**
     * Issue a brand-new access token via the password grant, using the
     * CMS-configured Pathao account credentials, and persist it.
     */
    private function issueToken(): array
    {
        $payload = [
            'client_id' => $this->requireSetting('pathao_client_id'),
            'client_secret' => $this->requireSetting('pathao_client_secret'),
            'grant_type' => 'password',
            'username' => $this->requireSetting('pathao_username'),
            'password' => $this->requireSetting('pathao_password'),
        ];

        Log::info('[Pathao] Issuing new access token');

        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->requestTimeout())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('/aladdin/api/v1/issue-token', $payload);

        return $this->persistToken($this->handleResponse($response, 'Issue token'));
    }

    private function refreshToken(string $refreshToken): ?array
    {
        $payload = [
            'client_id' => $this->requireSetting('pathao_client_id'),
            'client_secret' => $this->requireSetting('pathao_client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        Log::info('[Pathao] Refreshing access token');

        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->requestTimeout())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('/aladdin/api/v1/issue-token', $payload);

        if ($response->failed()) {
            Log::warning('[Pathao] Refresh token failed, falling back to password grant', [
                'http_status' => $response->status(),
            ]);

            return null;
        }

        return $this->persistToken($response->json() ?? []);
    }

    /** @param  array<string, mixed>  $tokenResponse */
    private function persistToken(array $tokenResponse): array
    {
        $accessToken = $tokenResponse['access_token'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Pathao did not return an access token.');
        }

        $stored = [
            'access_token' => $accessToken,
            'refresh_token' => $tokenResponse['refresh_token'] ?? null,
            'expires_at' => Carbon::now()->addSeconds((int) ($tokenResponse['expires_in'] ?? 0))->toIso8601String(),
        ];

        Setting::updateOrCreate(
            ['key' => self::TOKEN_SETTINGS_KEY],
            ['value' => $stored, 'group' => 'courier'],
        );

        return $stored;
    }

    private function accessToken(): string
    {
        $stored = Setting::where('key', self::TOKEN_SETTINGS_KEY)->first()?->value;

        if (is_array($stored) && ! empty($stored['access_token'])) {
            $expiresAt = isset($stored['expires_at']) ? Carbon::parse($stored['expires_at']) : null;

            // Still valid with a safety margin
            if ($expiresAt !== null && $expiresAt->isFuture() && ! $expiresAt->subMinutes(5)->isPast()) {
                return $stored['access_token'];
            }

            if (! empty($stored['refresh_token'])) {
                $refreshed = $this->refreshToken($stored['refresh_token']);
                if ($refreshed !== null) {
                    return $refreshed['access_token'];
                }
            }
        }

        return $this->issueToken()['access_token'];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->requestTimeout())
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->accessToken(),
            ]);
    }

    /**
     * BIZ-028: Pathao's test system unless the setting is exactly
     * 'production' (an empty/unset setting also means sandbox). Parcels
     * booked in sandbox look sent but are never collected, so callers warn.
     */
    public static function isSandbox(): bool
    {
        return Setting::where('key', 'pathao_environment')->first()?->value !== 'production';
    }

    private function baseUrl(): string
    {
        return rtrim(
            self::isSandbox()
                ? config('pathao.sandbox_base_url')
                : config('pathao.production_base_url'),
            '/',
        );
    }

    private function requestTimeout(): int
    {
        return $this->timeout > 0 ? $this->timeout : (int) config('pathao.timeout', 30);
    }

    private function requireSetting(string $key): string
    {
        $value = Setting::where('key', $key)->first()?->value;

        if (blank($value)) {
            throw new RuntimeException("Pathao courier is not fully configured. Missing setting: {$key}.");
        }

        return (string) $value;
    }

    private function handleResponse(Response $response, string $action): array
    {
        if ($response->failed()) {
            Log::error("[Pathao] {$action} failed", [
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = $response->json('message') ?? "Pathao {$action} failed with HTTP {$response->status()}.";

            throw new RuntimeException(is_string($message) ? $message : "Pathao {$action} failed.");
        }

        $json = $response->json();

        if (($json['type'] ?? 'success') !== 'success') {
            Log::error("[Pathao] {$action} returned an error payload", ['response' => $json]);

            throw new RuntimeException($json['message'] ?? "Pathao {$action} failed.");
        }

        return $json;
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

        // Pathao expects an 11-digit local number (01XXXXXXXXX); strip a leading country code.
        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }

    private function describeItems(Order $order): string
    {
        $names = $order->items->pluck('product_name')->filter()->unique()->values()->all();

        $description = implode(', ', $names);

        return mb_strlen($description) > 500 ? mb_substr($description, 0, 497).'...' : $description;
    }

    private function estimateWeight(Order $order): float
    {
        $min = (float) config('pathao.min_item_weight', 0.5);
        $max = (float) config('pathao.max_item_weight', 10.0);

        $total = $order->items->sum(
            fn ($item) => (float) ($item->product?->weight ?? $min) * $item->quantity,
        );

        return round(min($max, max($min, $total)), 2);
    }
}
