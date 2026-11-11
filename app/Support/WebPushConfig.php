<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\Setting;
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

    private static function stored(string $key): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
