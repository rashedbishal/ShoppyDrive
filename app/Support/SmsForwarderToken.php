<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The SMS-forwarder bearer token lives in Setting (like courier/gateway
 * credentials already do), not .env — Admin > SMS Forwarder's "Regenerate"
 * action updates it in place, with no production file rewrite or
 * config-cache rebuild involved.
 */
class SmsForwarderToken
{
    public const KEY = 'sms_forwarder_token';

    public static function current(): string
    {
        return (string) (Setting::where('key', self::KEY)->first()?->value ?? '');
    }

    public static function regenerate(): string
    {
        $token = Str::random(64);

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $token, 'group' => 'sms_forwarder']);

        return $token;
    }
}
