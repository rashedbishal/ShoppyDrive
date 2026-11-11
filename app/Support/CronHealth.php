<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Commands\DrainQueueCommand;
use App\Mail\CronRecoveredMail;
use App\Mail\CronStalledMail;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * OPS-002: tells the mother admin when the scheduler (cron) stops, without
 * any third-party service. A dead cron can't announce itself, so the check
 * piggybacks on ordinary customer page views instead (CheckCronHealth
 * middleware, run after the response has been sent): at most once a minute,
 * one visit asks "when did `app:drain-queue` last run?" and emails the mother
 * admin when the answer is more than STALL_SECONDS ago, once per outage, plus
 * one "recovered" email afterwards. Alerts go out synchronously, never
 * through the queue that is the thing that stopped.
 *
 * Only a heartbeat that exists and is stale counts as stalled. A missing one
 * (fresh install, local dev, `cache:clear` on deploy) is "unknown", never an
 * alarm — the next cron run writes it again within a minute.
 *
 * Everything stored in the cache is a plain int/array, never an object (the
 * file store has `serializable_classes` disabled).
 */
class CronHealth
{
    /** Matches the dashboard banner's 10-minute threshold. */
    public const STALL_SECONDS = 600;

    /** At most one health check per minute, however many visitors arrive. */
    private const CHECK_INTERVAL_SECONDS = 60;

    /** After a failed alert email, wait this long before trying again. */
    private const RETRY_AFTER_FAILED_MAIL_SECONDS = 600;

    private const CHECK_THROTTLE_KEY = 'ops:cron_health_checked';

    /** Present (as the Unix time the alert went out) while an outage has been announced. */
    private const ALERT_ACTIVE_KEY = 'ops:cron_alert_active';

    private const EVENTS_KEY = 'ops:cron_stall_events';

    private const MAX_EVENTS = 10;

    public static function isStalled(): bool
    {
        $secondsAgo = DrainQueueCommand::lastDrainedSecondsAgo();

        return $secondsAgo !== null && $secondsAgo > self::STALL_SECONDS;
    }

    /**
     * Everything the Admin > System Monitor page shows.
     *
     * @return array{
     *     status: 'ok'|'stalled'|'unknown',
     *     threshold_seconds: int,
     *     last_drained_seconds_ago: int|null,
     *     oldest_pending_job_seconds: int|null,
     *     pending_jobs: int,
     *     failed_jobs: int,
     *     alert_active_since: int|null,
     *     alert_email_configured: bool,
     *     external_ping_configured: bool,
     *     events: list<array{stalled_at: int, recovered_at: int|null}>,
     * }
     */
    public static function snapshot(): array
    {
        $lastDrained = DrainQueueCommand::lastDrainedSecondsAgo();
        $alertActiveSince = Cache::get(self::ALERT_ACTIVE_KEY);

        return [
            'status' => $lastDrained === null ? 'unknown' : (self::isStalled() ? 'stalled' : 'ok'),
            'threshold_seconds' => self::STALL_SECONDS,
            'last_drained_seconds_ago' => $lastDrained,
            'oldest_pending_job_seconds' => DrainQueueCommand::oldestPendingJobAgeSeconds(),
            'pending_jobs' => DB::table((string) config('queue.connections.database.table', 'jobs'))->count(),
            'failed_jobs' => DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))->count(),
            'alert_active_since' => is_int($alertActiveSince) ? $alertActiveSince : null,
            'alert_email_configured' => self::alertRecipient() !== null,
            'external_ping_configured' => HeartbeatPing::url() !== null,
            'events' => self::events(),
        ];
    }

    /**
     * Most recent first.
     *
     * @return list<array{stalled_at: int, recovered_at: int|null}>
     */
    public static function events(): array
    {
        $events = Cache::get(self::EVENTS_KEY);

        if (! is_array($events)) {
            return [];
        }

        return array_values(array_filter($events, fn ($event): bool => is_array($event) && is_int($event['stalled_at'] ?? null)));
    }

    /**
     * Never throws: this runs after a customer's response, and a mail or
     * cache problem here must not turn into an error for anything else.
     */
    public static function check(): void
    {
        try {
            if (! Cache::add(self::CHECK_THROTTLE_KEY, 1, self::CHECK_INTERVAL_SECONDS)) {
                return;
            }

            if (self::isStalled()) {
                self::announceStall();

                return;
            }

            // Healthy again: pull() hands back the flag only to the one
            // visitor who clears it, so exactly one recovery mail goes out.
            if (Cache::pull(self::ALERT_ACTIVE_KEY) !== null) {
                self::announceRecovery();
            }
        } catch (Throwable $e) {
            Log::warning('Cron health check failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }

    private static function announceStall(): void
    {
        $now = now()->getTimestamp();

        // add() is the atomic claim: only one visitor per outage gets past it.
        if (! Cache::add(self::ALERT_ACTIVE_KEY, $now, now()->addDays(30))) {
            return;
        }

        $recipient = self::alertRecipient();
        $secondsAgo = DrainQueueCommand::lastDrainedSecondsAgo() ?? self::STALL_SECONDS;

        try {
            if ($recipient === null) {
                throw new \RuntimeException('No mother admin configured.');
            }

            Mail::to($recipient)->send(new CronStalledMail(minutesSilent: (int) floor($secondsAgo / 60)));
        } catch (Throwable $e) {
            // Release the claim so a later visit retries, but not on every
            // visit: a broken mail setup would otherwise be tried each minute.
            Cache::forget(self::ALERT_ACTIVE_KEY);
            Cache::put(self::CHECK_THROTTLE_KEY, 1, self::RETRY_AFTER_FAILED_MAIL_SECONDS);
            Log::warning('Could not send the cron-stalled alert email.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return;
        }

        self::recordEvent(['stalled_at' => $now, 'recovered_at' => null]);
    }

    private static function announceRecovery(): void
    {
        $now = now()->getTimestamp();
        $events = self::events();

        if ($events !== [] && $events[0]['recovered_at'] === null) {
            $events[0]['recovered_at'] = $now;
            Cache::forever(self::EVENTS_KEY, $events);
        }

        $recipient = self::alertRecipient();

        if ($recipient === null) {
            return;
        }

        try {
            Mail::to($recipient)->send(new CronRecoveredMail);
        } catch (Throwable $e) {
            Log::warning('Could not send the cron-recovered email.', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{stalled_at: int, recovered_at: int|null}  $event
     */
    private static function recordEvent(array $event): void
    {
        Cache::forever(self::EVENTS_KEY, array_slice([$event, ...self::events()], 0, self::MAX_EVENTS));
    }

    private static function alertRecipient(): ?string
    {
        return Admin::where('is_mother', true)->value('email') ?: null;
    }
}
