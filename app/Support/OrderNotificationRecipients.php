<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\Setting;

/**
 * Where the shop-side order emails go: the "new order" alert and the
 * "order cancelled" alert. Each has its own address in Admin > Settings
 * (Setting keys below). A configured address replaces the default; when a
 * field is empty (or holds something that is not a valid address), the mail
 * goes to the main (mother) admin's login email, which is where the new-order
 * alert always went before these settings existed. The customer's own
 * confirmation and cancellation emails are unaffected.
 *
 * Deliberately not in LayoutCache::PUBLIC_SETTING_KEYS: these are private and
 * must never be sent to the storefront.
 */
class OrderNotificationRecipients
{
    public const NEW_ORDER_KEY = 'order_notification_email';

    public const CANCELLATION_KEY = 'order_cancellation_email';

    public static function newOrder(): ?string
    {
        return self::configured(self::NEW_ORDER_KEY) ?? self::motherAdmin();
    }

    public static function cancellation(): ?string
    {
        return self::configured(self::CANCELLATION_KEY) ?? self::motherAdmin();
    }

    private static function configured(string $key): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        if (! is_string($value)) {
            return null;
        }

        $email = trim($value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private static function motherAdmin(): ?string
    {
        return Admin::where('is_mother', true)->value('email') ?: null;
    }
}
