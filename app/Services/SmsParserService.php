<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsPattern;
use Illuminate\Support\Facades\Cache;

class SmsParserService
{
    /**
     * Parse an incoming SMS and return provider, transaction ID, and amount.
     *
     * @return array{provider: string, transaction_id: string|null, amount: float|null}
     */
    public function parse(string $content): array
    {
        foreach ($this->activePatterns() as $pattern) {
            if (stripos($content, $pattern['identifier']) === false) {
                continue;
            }

            return [
                'provider' => $pattern['provider'],
                'transaction_id' => $this->extractString($content, $pattern['txn_id_regex']),
                'amount' => $this->extractAmount($content, $pattern['amount_regex']),
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
     * @return array<int, array{provider:string, identifier:string, txn_id_regex:string, amount_regex:string}>
     */
    private function activePatterns(): array
    {
        return Cache::remember(
            'sms_patterns_active',
            300,
            fn () => SmsPattern::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['provider', 'identifier', 'txn_id_regex', 'amount_regex'])
                ->map(fn ($p) => [
                    'provider' => $p->provider,
                    'identifier' => $p->identifier,
                    'txn_id_regex' => $p->txn_id_regex,
                    'amount_regex' => $p->amount_regex,
                ])
                ->all(),
        );
    }

    private function extractString(string $content, string $regex): ?string
    {
        if (@preg_match('/'.$regex.'/i', $content, $matches) && isset($matches[1])) {
            $value = strtoupper(trim($matches[1]));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function extractAmount(string $content, string $regex): ?float
    {
        if (@preg_match('/'.$regex.'/i', $content, $matches) && isset($matches[1])) {
            $value = (float) str_replace(',', '', $matches[1]);

            return $value > 0 ? $value : null;
        }

        return null;
    }
}
