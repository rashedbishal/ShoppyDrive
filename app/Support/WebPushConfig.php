<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\VAPID;

/**
 * The VAPID key pair that identifies this site to the browsers' push services
 * (Google, Mozilla, Apple) so admin SMS notifications can be delivered. Like
 * every other credential here it lives in Setting and is created from the
 * admin panel (Admin > SMS Forwarder, mother admin only), never typed by hand
 * and deliberately NOT read from `.env`.
 *
 * The private key must never leave the server, and neither key is in
 * LayoutCache::PUBLIC_SETTING_KEYS. Only the public key is given to the
 * (logged-in admin) browser, which needs it to subscribe.
 */
class WebPushConfig
{
    public const PUBLIC_KEY_SETTING = 'webpush_public_key';

    public const PRIVATE_KEY_SETTING = 'webpush_private_key';

    public static function publicKey(): ?string
    {
        return self::stored(self::PUBLIC_KEY_SETTING);
    }

    public static function privateKey(): ?string
    {
        return self::stored(self::PRIVATE_KEY_SETTING);
    }

    public static function subject(): string
    {
        return (string) config('services.webpush.subject');
    }

    public static function isConfigured(): bool
    {
        return self::publicKey() !== null && self::privateKey() !== null;
    }

    /**
     * Creates and saves a brand-new pair. Every browser subscribed with the
     * previous pair stops working with it, so those subscriptions are removed
     * in the same transaction; each admin then enables notifications again.
     *
     * @return int how many old subscriptions were removed
     */
    public static function generate(): int
    {
        $keys = VAPID::createVapidKeys();

        return DB::transaction(function () use ($keys): int {
            Setting::updateOrCreate(['key' => self::PUBLIC_KEY_SETTING], ['value' => $keys['publicKey'], 'group' => 'notifications']);
            Setting::updateOrCreate(['key' => self::PRIVATE_KEY_SETTING], ['value' => $keys['privateKey'], 'group' => 'notifications']);

            return PushSubscription::query()->delete();
        });
    }

    /**
     * The outcome of the most recent push attempt, shown on Admin > SMS
     * Forwarder. Kept in the durable limiter store (like the phone heartbeat)
     * so a deploy's `cache:clear` doesn't wipe it.
     *
     * @param  'sms'|'test'  $kind
     * @param  array{sent: int, expired: int, failed: list<string>}  $result
     */
    public static function recordResult(string $kind, array $result): void
    {
        Cache::store(config('cache.limiter') ?: config('cache.default'))->forever('webpush_last_result', [
            'kind' => $kind,
            'at' => now()->getTimestamp(),
            'sent' => $result['sent'],
            'expired' => $result['expired'],
            'failed' => array_slice($result['failed'], 0, 3),
        ]);
    }

    /**
     * @return array{kind: string, at: string, sent: int, expired: int, failed: list<string>}|null
     */
    public static function lastResult(): ?array
    {
        $value = Cache::store(config('cache.limiter') ?: config('cache.default'))->get('webpush_last_result');

        if (! is_array($value) || ! is_int($value['at'] ?? null)) {
            return null;
        }

        return [
            'kind' => (string) ($value['kind'] ?? 'sms'),
            'at' => Carbon::createFromTimestamp($value['at'])->toIso8601String(),
            'sent' => (int) ($value['sent'] ?? 0),
            'expired' => (int) ($value['expired'] ?? 0),
            'failed' => array_values(array_map('strval', (array) ($value['failed'] ?? []))),
        ];
    }

    private static function stored(string $key): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
