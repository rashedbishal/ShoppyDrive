<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-002: the outside half of "tell someone when cron or the queue stops".
 * If the scheduler dies, nothing inside this app can notice or say so, so
 * `app:drain-queue` (run by cron every minute) pings a URL the mother admin
 * pastes into Admin > Security from any dead-man's-switch monitor
 * (Healthchecks.io, UptimeRobot heartbeat, BetterStack, ...). The monitor
 * alerts when the pings stop arriving. Like every other third-party
 * credential here, the URL lives in Setting, never .env, and is optional:
 * unset means no ping and nothing else changes.
 */
class HeartbeatPing
{
    public const SETTING_KEY = 'heartbeat_ping_url';

    /** Kept short: this runs inside the every-minute drain command. */
    private const TIMEOUT_SECONDS = 5;

    public static function url(): ?string
    {
        $url = Setting::where('key', self::SETTING_KEY)->value('value');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * A blank value removes the setting, switching the ping off.
     */
    public static function save(?string $url): void
    {
        if ($url === null || trim($url) === '') {
            Setting::where('key', self::SETTING_KEY)->get()->each->delete();

            return;
        }

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => trim($url), 'group' => 'operations'],
        );
    }

    /**
     * Never throws and never logs the URL (monitor ping URLs act as secrets):
     * a monitor outage or a bad URL must not break queue draining.
     */
    public static function send(): void
    {
        $url = self::url();

        if ($url === null) {
            return;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);

            if ($response->failed()) {
                Log::warning('Heartbeat ping was rejected by the monitoring service.', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::warning('Heartbeat ping could not be sent.', ['exception' => $e::class]);
        }
    }
}
