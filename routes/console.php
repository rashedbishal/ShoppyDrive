<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pathao:sync-statuses')->everyFifteenMinutes()->withoutOverlapping();

// Delay-gated referral/order points — day-granularity, so once a day is
// plenty, but it runs in Asia/Dhaka so "once a day" lines up with the same
// calendar day the reward delay itself is computed in.
Schedule::command('rewards:grant-due')->dailyAt('01:00')->timezone('Asia/Dhaka')->withoutOverlapping();

Schedule::command('cart:remind-abandoned')->hourly()->withoutOverlapping();

// Hostinger shared hosting can't keep a worker process alive, so the
// every-minute cron that drives this scheduler also drains the database
// queue. The worker exits once the queue is empty (or after 50s, before the
// next tick); the 5-minute lock means a worker the host kills only blocks new
// ones briefly; --timeout stays under the connection's 90s retry_after so a
// slow job is never handed out twice. Only the `default` queue is read, so
// jobs moved to any other queue name are left alone. Kept last so jobs queued
// by the tasks above go out in the same run.
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --backoff=30 --timeout=45')
    ->everyMinute()
    ->withoutOverlapping(5);
