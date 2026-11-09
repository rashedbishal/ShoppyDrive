<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReceivedSms;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * OPS-005: the Android forwarder phone (a single point of failure) can go
 * silent — dead battery, no signal, app crashed — without ever producing an
 * unmatched SMS to count. The forwarder app's foreground service now pings
 * `POST /api/sms/heartbeat` every 1-2 minutes purely to prove it's alive, so
 * a gap means the phone itself, not just the absence of a bKash/Nagad
 * payment during that stretch.
 */
class SmsForwarderHeartbeat
{
    private const CACHE_KEY = 'sms_forwarder_last_heartbeat';

    /**
     * Five minutes of missed heartbeats (at a 1-2 minute ping interval) is
     * loose enough to tolerate a slow network blip but tight enough to
     * catch a dead/off phone within a few minutes, not hours.
     */
    private const DOWN_AFTER_MINUTES = 5;

    /**
     * Before any phone has ever sent a heartbeat (old app version not yet
     * reinstalled, or a fresh setup), fall back to the original, far more
     * conservative "no SMS received at all" signal — this must never start
     * reporting false positives just because the heartbeat endpoint has
     * never been called yet.
     */
    private const FALLBACK_SILENT_MINUTES = 360;

    public static function record(): void
    {
        Cache::forever(self::CACHE_KEY, now()->getTimestamp());
    }

    public static function lastHeartbeatAt(): ?CarbonInterface
    {
        $value = Cache::get(self::CACHE_KEY);

        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }

    public static function isDown(): bool
    {
        $lastHeartbeat = self::lastHeartbeatAt();

        if ($lastHeartbeat !== null) {
            return now()->diffInMinutes($lastHeartbeat, true) >= self::DOWN_AFTER_MINUTES;
        }

        $lastReceivedAt = ReceivedSms::max('received_at');

        return $lastReceivedAt !== null
            && now()->diffInMinutes($lastReceivedAt, true) >= self::FALLBACK_SILENT_MINUTES;
    }
}
