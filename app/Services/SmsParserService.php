<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsPattern;
use App\Support\SmsRegex;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsParserService
{
    /**
     * Parse an incoming SMS and return provider, transaction ID, and amount.
     *
     * $sender is checked against each candidate pattern's `sender_regex`
     * (FSA-02). A pattern with no `sender_regex` configured stays
     * unrestricted — the allowlist only takes effect once an admin sets one.
     * A pattern whose `sender_regex` doesn't match this sender is skipped
     * entirely, never falling back to a body-only match: if no remaining
     * pattern matches, this returns 'unknown' with nothing parsed, exactly
     * as if the identifier itself hadn't matched.
     *
     * @return array{provider: string, transaction_id: string|null, amount: float|null}
     */
    public function parse(string $content, string $sender = ''): array
    {
        if (mb_strlen($content) > SmsRegex::MAX_CONTENT_LENGTH) {
            return ['provider' => 'unknown', 'transaction_id' => null, 'amount' => null];
        }

        foreach ($this->activePatterns() as $pattern) {
            if (stripos($content, $pattern['identifier']) === false) {
                continue;
            }

            if (! $this->senderAllowed($sender, $pattern)) {
                continue;
            }

            return [
                'provider' => $pattern['provider'],
                'transaction_id' => $this->extractString($content, $pattern, 'txn_id_regex'),
                'amount' => $this->extractAmount($content, $pattern, 'amount_regex'),
            ];
        }

        return [
            'provider' => 'unknown',
            'transaction_id' => null,
            'amount' => null,
        ];
    }

    /**
     * Return active patterns as plain arrays to avoid Eloquent model serialization issues in cache.
     *
     * @return array<int, array{id:int, provider:string, identifier:string, sender_regex:string|null, txn_id_regex:string, amount_regex:string}>
     */
    private function activePatterns(): array
    {
        return Cache::remember(
            'sms_patterns_active',
            300,
            fn () => SmsPattern::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'provider', 'identifier', 'sender_regex', 'txn_id_regex', 'amount_regex'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'provider' => $p->provider,
                    'identifier' => $p->identifier,
                    'sender_regex' => $p->sender_regex,
                    'txn_id_regex' => $p->txn_id_regex,
                    'amount_regex' => $p->amount_regex,
                ])
                ->all(),
        );
    }

    /**
     * @param  array{id?:int, sender_regex:string|null}  $pattern
     */
    private function senderAllowed(string $sender, array $pattern): bool
    {
        if ($pattern['sender_regex'] === null || $pattern['sender_regex'] === '') {
            return true;
        }

        $matches = SmsRegex::matches($pattern['sender_regex'], $sender, $failure);

        if ($failure !== null) {
            Log::warning('[SMS] A sender pattern could not be evaluated', [
                'pattern_id' => $pattern['id'] ?? null,
                'error' => $failure,
            ]);

            return false;
        }

        return $matches;
    }

    /**
     * @param  array{id?:int, txn_id_regex:string, amount_regex:string}  $pattern
     */
    private function extractString(string $content, array $pattern, string $field): ?string
    {
        $value = $this->capture($content, $pattern, $field);

        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array{id?:int, txn_id_regex:string, amount_regex:string}  $pattern
     */
    private function extractAmount(string $content, array $pattern, string $field): ?float
    {
        $value = $this->capture($content, $pattern, $field);

        if ($value === null) {
            return null;
        }

        $value = (float) str_replace(',', '', $value);

        return $value > 0 ? $value : null;
    }

    /**
     * A regex that can't run yields null, which leaves nothing for a payment
     * to match. Logged by pattern only: the SMS holds transaction IDs, phone
     * numbers and amounts.
     *
     * @param  array{id?:int, txn_id_regex:string, amount_regex:string}  $pattern
     */
    private function capture(string $content, array $pattern, string $field): ?string
    {
        $value = SmsRegex::firstCapture($pattern[$field], $content, $failure);

        if ($failure !== null) {
            Log::warning('[SMS] A pattern could not be evaluated', [
                'pattern_id' => $pattern['id'] ?? null,
                'field' => $field,
                'error' => $failure,
            ]);
        }

        return $value;
    }
}
