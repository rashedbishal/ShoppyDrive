<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReceivedSms;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
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

    /**
     * The last ping is kept in the same durable store the rate limiters use
     * (`cache.limiter`, the database cache in production), NOT the default
     * cache: every deploy runs `cache:clear`, which empties the default
     * store, and a forgotten ping silently switched this check to the slow
     * 6-hour fallback — so a phone that was already off kept bKash/Nagad
     * available to customers after each deploy until it happened to ping
     * again. `cache:clear` leaves the database store alone.
     */
    private static function store(): Repository
    {
        return Cache::store(config('cache.limiter') ?: config('cache.default'));
    }

    public static function record(): void
    {
        self::store()->forever(self::CACHE_KEY, now()->getTimestamp());
    }

    public static function lastHeartbeatAt(): ?CarbonInterface
    {
        $value = self::store()->get(self::CACHE_KEY);

        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }

    /**
     * What Admin > SMS Forwarder shows, so staff can see why bKash/Nagad are
     * (not) offered instead of guessing.
     *
     * @return array{
     *     is_down: bool,
     *     mode: 'heartbeat'|'fallback',
     *     last_heartbeat_at: string|null,
     *     minutes_since_heartbeat: int|null,
     *     last_sms_at: string|null,
     *     down_after_minutes: int,
     *     fallback_silent_hours: int,
     * }
     */
    public static function status(): array
    {
        $lastHeartbeat = self::lastHeartbeatAt();
        $lastReceivedAt = ReceivedSms::max('received_at');

        return [
            'is_down' => self::isDown(),
            'mode' => $lastHeartbeat !== null ? 'heartbeat' : 'fallback',
            'last_heartbeat_at' => $lastHeartbeat?->toIso8601String(),
            'minutes_since_heartbeat' => $lastHeartbeat !== null ? (int) floor(now()->diffInMinutes($lastHeartbeat, true)) : null,
            'last_sms_at' => $lastReceivedAt !== null ? Carbon::parse($lastReceivedAt)->toIso8601String() : null,
            'down_after_minutes' => self::DOWN_AFTER_MINUTES,
            'fallback_silent_hours' => intdiv(self::FALLBACK_SILENT_MINUTES, 60),
        ];
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
