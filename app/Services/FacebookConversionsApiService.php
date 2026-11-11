<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MetaConversionsApiException;
use App\Models\MetaTrackingEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\FacebookConversionsApiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The single place a Meta (Facebook) Conversions API request is built and
 * sent — see PathaoCourierService/SteadfastCourierService for the same "one
 * service owns one external integration" shape. It sends one stored
 * MetaTrackingEvent and throws MetaConversionsApiException on any failure;
 * deciding whether to retry is SendMetaTrackingEventJob's job.
 *
 * Customer identifiers are read from the order/account at send time and
 * SHA-256 hashed here; the IP address, user agent, fbp and fbc are sent
 * unhashed, as Meta requires.
 */
class FacebookConversionsApiService
{
    public const CURRENCY = 'BDT';

    public function send(MetaTrackingEvent $event): void
    {
        if (! FacebookConversionsApiConfig::isAvailable()) {
            throw new MetaConversionsApiException('The Conversions API is switched off or not configured.', retryable: false);
        }

        $payload = ['data' => [$this->buildEvent($event)]];
        $testEventCode = FacebookConversionsApiConfig::testEventCode();

        if ($testEventCode !== null) {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::asJson()->timeout(15)->post(
                sprintf(
                    'https://graph.facebook.com/%s/%s/events',
                    config('services.meta_conversions_api.api_version'),
                    FacebookConversionsApiConfig::pixelId(),
                ),
                [...$payload, 'access_token' => FacebookConversionsApiConfig::accessToken()],
            );
        } catch (ConnectionException) {
            throw new MetaConversionsApiException('Could not reach the Graph API (timeout or connection error).', retryable: true);
        }

        if (! $response->successful()) {
            throw $this->rejection($response);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEvent(MetaTrackingEvent $event): array
    {
        $order = $event->order_id !== null ? Order::with(['items', 'user'])->find($event->order_id) : null;

        return array_filter([
            'event_name' => $event->event_name,
            'event_time' => $event->event_time->timestamp,
            'event_id' => $event->event_id,
            'action_source' => $event->action_source,
            'event_source_url' => $event->event_source_url,
            'user_data' => $this->buildUserData($event, $order),
            'custom_data' => $event->event_name === 'Purchase' && $order !== null
                ? $this->purchaseCustomData($order)
                : $event->custom_data,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserData(MetaTrackingEvent $event, ?Order $order): array
    {
        $user = $order?->user ?? ($event->user_id !== null ? User::find($event->user_id) : null);
        $address = (array) ($order?->shipping_address ?? []);

        $email = $user?->email ?: ($address['email'] ?? null);
        $phone = ($address['phone'] ?? null) ?: $user?->phone;
        $name = ($address['name'] ?? null) ?: $user?->name;
        [$firstName, $lastName] = $this->splitName($name);

        $hashed = [
            'em' => $this->normalizeEmail($email),
            'ph' => $this->normalizePhone($phone),
            'fn' => $this->normalizeName($firstName),
            'ln' => $this->normalizeName($lastName),
            'ct' => $this->normalizeLocation($address['city'] ?? null),
            'st' => $this->normalizeLocation($address['state'] ?? null),
            'zp' => $this->normalizeLocation($address['postal_code'] ?? null),
            'country' => $order !== null ? 'bd' : null,
            'external_id' => $user !== null ? (string) $user->id : null,
        ];

        $userData = [];

        foreach ($hashed as $key => $value) {
            if ($value !== null && $value !== '') {
                $userData[$key] = [hash('sha256', $value)];
            }
        }

        foreach (['client_ip_address', 'client_user_agent', 'fbp', 'fbc'] as $key) {
            if (filled($event->{$key})) {
                $userData[$key] = $event->{$key};
            }
        }

        return $userData;
    }

    /**
     * Built from the order itself, never from anything the browser sent.
     *
     * @return array<string, mixed>
     */
    private function purchaseCustomData(Order $order): array
    {
        $contents = $order->items->map(fn (OrderItem $item): array => [
            'id' => $item->product_id !== null ? (string) $item->product_id : (string) ($item->sku ?: 'item-'.$item->id),
            'quantity' => (int) $item->quantity,
            'item_price' => round((float) $item->price, 2),
        ])->values()->all();

        return array_filter([
            'currency' => self::CURRENCY,
            'value' => round((float) $order->total, 2),
            'order_id' => $order->order_number,
            'content_type' => 'product',
            'content_ids' => array_values(array_unique(array_column($contents, 'id'))),
            'contents' => $contents,
            'num_items' => (int) $order->items->sum('quantity'),
            'coupon' => $order->coupon_code ?: null,
        ], fn ($value): bool => $value !== null);
    }

    private function rejection(Response $response): MetaConversionsApiException
    {
        $error = (array) $response->json('error', []);
        $isTransient = (bool) ($error['is_transient'] ?? false);
        $retryable = $isTransient || $response->status() === 429 || $response->serverError();

        $summary = trim(sprintf(
            'HTTP %d%s%s: %s',
            $response->status(),
            isset($error['code']) ? ' code '.$error['code'] : '',
            isset($error['error_subcode']) ? '/'.$error['error_subcode'] : '',
            (string) ($error['error_user_msg'] ?? $error['message'] ?? 'no error message'),
        ));

        return new MetaConversionsApiException(Str::limit($summary, 480, ''), $retryable, $response->status());
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return [null, null];
        }

        $firstName = array_shift($parts);

        return [$firstName, $parts === [] ? null : implode(' ', $parts)];
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return str_contains($email, '@') ? $email : null;
    }

    /**
     * Digits only, with the 880 country code and no leading zero, e.g.
     * 01712345678 -> 8801712345678.
     */
    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (preg_match('/^01\d{9}$/', $digits) === 1) {
            return '880'.substr($digits, 1);
        }

        if (preg_match('/^8801\d{9}$/', $digits) === 1) {
            return $digits;
        }

        return null;
    }

    /** Lowercase, letters only (Meta's fn/ln rule), keeping non-Latin letters. */
    private function normalizeName(?string $name): ?string
    {
        $normalized = preg_replace('/[^\p{L}]+/u', '', mb_strtolower(trim((string) $name))) ?? '';

        return $normalized === '' ? null : $normalized;
    }

    /** Lowercase with spaces and punctuation removed (Meta's ct/st/zp rule). */
    private function normalizeLocation(?string $value): ?string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim((string) $value))) ?? '';

        return $normalized === '' ? null : $normalized;
    }
}
