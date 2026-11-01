<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZiniPayService
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly int $timeout;

    public function __construct()
    {
        $this->apiKey = config('zinipay.api_key', '');
        $this->baseUrl = rtrim(config('zinipay.base_url', 'https://api.zinipay.com'), '/');
        $this->timeout = config('zinipay.timeout', 30);
    }

    /**
     * Verify a ZiniPay payment by val_id (our order_number).
     *
     * @return array{
     *   cus_name: string,
     *   cus_email: string,
     *   amount: int|float,
     *   invoice_id: string,
     *   val_id: string,
     *   payment_method: string,
     *   transaction_id: string,
     *   status: string,
     * }
     *
     * @throws RuntimeException on gateway error
     */
    public function verifyPayment(string $valId): array
    {
        $this->ensureApiKeyConfigured();

        Log::info('[ZiniPay] Verifying payment', ['val_id' => $valId]);

        $response = Http::withHeaders($this->headers())
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/v1/payment/verify", ['invoice_id' => $valId]);

        if ($response->failed()) {
            Log::error('[ZiniPay] Verification HTTP error', [
                'val_id' => $valId,
                'http_status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("ZiniPay verification returned HTTP {$response->status()}.");
        }

        $json = $response->json();

        Log::info('[ZiniPay] Verification response', ['val_id' => $valId, 'status' => $json['status'] ?? null]);

        return $json;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'zini-api-key' => $this->apiKey,
        ];
    }

    private function ensureApiKeyConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ZiniPay API key is not configured. Set ZINIPAY_API_KEY in .env');
        }
    }
}
