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
