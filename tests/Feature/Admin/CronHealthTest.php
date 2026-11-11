<?php

declare(strict_types=1);

use App\Console\Commands\DrainQueueCommand;
use App\Mail\CronRecoveredMail;
use App\Mail\CronStalledMail;
use App\Models\Admin;
use App\Support\CronHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

function cronLastRanMinutesAgo(int $minutes): void
{
    Cache::forever(DrainQueueCommand::LAST_RUN_CACHE_KEY, now()->subMinutes($minutes)->getTimestamp());
}

// ── Email alerts ─────────────────────────────────────────────────────────────

test('a customer visit after 10+ minutes of cron silence emails the mother admin once', function () {
    Mail::fake();
    $mother = createMotherAdmin(['email' => 'owner@example.com']);
    cronLastRanMinutesAgo(15);

    $this->get(route('home'))->assertOk();

    Mail::assertSent(CronStalledMail::class, fn (CronStalledMail $mail) => $mail->hasTo('owner@example.com') && $mail->minutesSilent === 15);
    Mail::assertSentCount(1);
    expect(CronHealth::events())->toHaveCount(1)->and(CronHealth::events()[0]['recovered_at'])->toBeNull();

    // Later visits during the same outage never send it again.
    travel(61)->seconds();
    $this->get(route('home'))->assertOk();
    travel(5)->minutes();
    $this->get(route('home'))->assertOk();

    Mail::assertSentCount(1);
});

test('9 minutes of silence is not yet an outage', function () {
    Mail::fake();
    createMotherAdmin();
    cronLastRanMinutesAgo(9);

    $this->get(route('home'))->assertOk();

    Mail::assertNothingSent();
});

test('a missing heartbeat is unknown, never an alarm', function () {
    Mail::fake();
    createMotherAdmin();

    $this->get(route('home'))->assertOk();
    expect(CronHealth::snapshot()['status'])->toBe('unknown');

    Mail::assertNothingSent();
});

test('the check runs at most once a minute however many visitors arrive', function () {
    Mail::fake();
    createMotherAdmin();
    cronLastRanMinutesAgo(15);
    CronHealth::check();
    Cache::forget('ops:cron_alert_active');

    // Throttled: this second call within the minute does nothing at all.
    CronHealth::check();

    Mail::assertSentCount(1);
});

test('one recovery email goes out when cron is back, and the outage is closed in the history', function () {
    Mail::fake();
    createMotherAdmin(['email' => 'owner@example.com']);
    cronLastRanMinutesAgo(15);
    CronHealth::check();

    cronLastRanMinutesAgo(0);
    travel(61)->seconds();
    CronHealth::check();
    travel(61)->seconds();
    CronHealth::check();

    Mail::assertSent(CronRecoveredMail::class, fn (CronRecoveredMail $mail) => $mail->hasTo('owner@example.com'));
    Mail::assertSentCount(2);
    expect(CronHealth::events()[0]['recovered_at'])->not->toBeNull();
});

test('a healthy site sends no recovery email when nothing was ever announced', function () {
    Mail::fake();
    createMotherAdmin();
    cronLastRanMinutesAgo(0);

    CronHealth::check();

    Mail::assertNothingSent();
});

test('a broken mail setup never breaks the page, and is retried only after 10 minutes', function () {
    createMotherAdmin();
    cronLastRanMinutesAgo(15);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    $this->get(route('home'))->assertOk();

    expect(Cache::has('ops:cron_alert_active'))->toBeFalse()
        ->and(CronHealth::events())->toBe([]);

    // Within the 10-minute back-off nothing is attempted (the mock allows only one call).
    travel(5)->minutes();
    CronHealth::check();
});

test('with no mother admin there is nobody to email and nothing breaks', function () {
    Mail::fake();
    cronLastRanMinutesAgo(15);

    $this->get(route('home'))->assertOk();

    Mail::assertNothingSent();
});

// ── Admin > System Monitor ───────────────────────────────────────────────────

test('the monitor page reports the status, and the mother admin can open it', function () {
    cronLastRanMinutesAgo(2);

    actingAs(createMotherAdmin(), 'admin')
        ->get(route('admin.monitor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/monitor/index')
            ->where('monitor.status', 'ok')
            ->where('monitor.threshold_seconds', 600)
            ->where('monitor.failed_jobs', 0));

    cronLastRanMinutesAgo(30);

    actingAs(createMotherAdmin(['email' => 'm2@example.com']), 'admin')
        ->get(route('admin.monitor.index'))
        ->assertInertia(fn (Assert $page) => $page->where('monitor.status', 'stalled'));
});

test('an admin without the Failed Jobs section cannot open the monitor', function () {
    $staff = Admin::create(['name' => 'No Access', 'email' => 'noaccess@example.com', 'password' => 'password', 'permissions' => []]);

    actingAs($staff, 'admin')->get(route('admin.monitor.index'))->assertForbidden();
});

test('an admin granted Failed Jobs can open the monitor', function () {
    $staff = Admin::create(['name' => 'Jobs', 'email' => 'jobsview@example.com', 'password' => 'password', 'permissions' => ['failed_jobs' => ['view']]]);

    actingAs($staff, 'admin')->get(route('admin.monitor.index'))->assertOk();
});
