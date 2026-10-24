<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\BangladeshDivisions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Best-effort IP-to-division lookup for region-targeted themes, using a free,
 * keyless geolocation endpoint. BD mobile/ISP IP geolocation is frequently
 * inaccurate (often resolves to Dhaka regardless of the visitor's real
 * location) — this is an inherent limitation of IP-based geolocation, not a
 * bug to fix here.
 */
class GeoLocationService
{
    /**
     * Known aliases the geolocation provider may return for a division name.
     *
     * @var array<string, string>
     */
    private const REGION_ALIASES = [
        'chittagong' => 'Chattogram',
        'barisal' => 'Barishal',
    ];

    public function divisionForIp(string $ip): ?string
    {
        if ($ip === '' || $this->isPrivateOrLocal($ip)) {
            return null;
        }

        return Cache::remember(
            "geoip:division:{$ip}",
            now()->addHours(12),
            fn () => $this->lookup($ip),
        );
    }

    private function lookup(string $ip): ?string
    {
        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,regionName',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('status') !== 'success') {
            return null;
        }

        $regionName = $response->json('regionName');

        return is_string($regionName) ? $this->normalizeDivision($regionName) : null;
    }

    private function normalizeDivision(string $regionName): ?string
    {
        $name = trim(str_ireplace('Division', '', $regionName));
        $alias = self::REGION_ALIASES[mb_strtolower($name)] ?? $name;

        foreach (BangladeshDivisions::ALL as $division) {
            if (mb_strtolower($division) === mb_strtolower($alias)) {
                return $division;
            }
        }

        return null;
    }

    private function isPrivateOrLocal(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
