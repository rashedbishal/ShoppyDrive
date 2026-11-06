<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every withoutOverlapping() below passes `false` as its second argument so
// the scheduler doesn't register pcntl_signal() handlers to release the lock
// on SIGTERM/SIGINT/SIGQUIT. Hostinger shared hosting loads the pcntl
// extension but disables pcntl_signal(), and Laravel only checks
// extension_loaded('pcntl') before calling it — so with the default `true`,
// every scheduled event threw "Call to undefined function pcntl_signal()"
// before it started. A lock left behind by a killed run still expires after
// the first argument's number of minutes.
Schedule::command('pathao:sync-statuses')->everyFifteenMinutes()->withoutOverlapping(1440, false);

// Delay-gated referral/order points — day-granularity, so once a day is
// plenty, but it runs in Asia/Dhaka so "once a day" lines up with the same
// calendar day the reward delay itself is computed in.
Schedule::command('rewards:grant-due')->dailyAt('01:00')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);

Schedule::command('cart:remind-abandoned')->hourly()->withoutOverlapping(1440, false);

// MED-20 Tier 1: converted PendingCheckout rows are pure leftover bookkeeping
// once their Order exists, so once a day is plenty. Tier 2 (unconverted,
// expired rows) is deliberately never touched by this command.
Schedule::command('pending-checkouts:prune')->dailyAt('02:00')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);

// FSA-13 backstop: newsletter uploads normally go with their batch's `then`
// callback; this clears finished, never-queued and abandoned leftovers.
Schedule::command('newsletter:prune-attachments')->dailyAt('03:00')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);

// DB-002: owner-approved retention (orders, payments, refunds and accounts are
// never touched). 720 hours = 30 days. activitylog:clean needs --force, or it
// stops at a confirmation prompt in production and silently does nothing; its
// 365 days come from config/activitylog.php. Unfinished batches need their own
// option, or queue:prune-batches keeps them forever.
Schedule::command('data:prune')->dailyAt('03:30')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);
Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:40')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);
Schedule::command('queue:prune-batches --hours=720 --unfinished=720 --cancelled=720')->dailyAt('03:45')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);
Schedule::command('activitylog:clean --force')->dailyAt('03:50')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);

// OPS-001: keeps /sitemap.xml current for search engines.
Schedule::command('sitemap:generate')->dailyAt('04:00')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false);

// BIZ-027: fallback for the Steadfast webhook, so a missed or rejected message
// can't leave a parcel's order stuck.
Schedule::command('steadfast:sync-statuses')->everyThirtyMinutes()->withoutOverlapping(1440, false);

// Hostinger shared hosting can't keep a worker process alive, and its
// disabled pcntl_signal() crashes `queue:work`'s daemon mode, so the
// every-minute cron that drives this scheduler drains the database queue
// with app:drain-queue instead (see DrainQueueCommand). It stops once the
// `default` queue is empty or after 50s; the 5-minute lock means a run the
// host kills only blocks new ones briefly. Jobs moved to any other queue name
// are left alone. Kept last so jobs queued by the tasks above go out in the
// same run.
Schedule::command('app:drain-queue')
    ->everyMinute()
    ->withoutOverlapping(5, false);
