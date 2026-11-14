<?php

declare(strict_types=1);

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Every scheduled event, with the PHP binary and `artisan` prefix stripped
 * from its command so assertions don't depend on which PHP binary runs the
 * suite.
 *
 * @return list<array{command: string, expression: string, withoutOverlapping: bool, expiresAt: int, releaseOnTerminationSignals: bool, timezone: string|null}>
 */
function scheduledEvents(): array
{
    return array_map(fn (ScheduledEvent $event): array => [
        'command' => Str::after($event->command, "'artisan' "),
        'expression' => $event->expression,
        'withoutOverlapping' => $event->withoutOverlapping,
        'expiresAt' => $event->expiresAt,
        'releaseOnTerminationSignals' => $event->releaseOnTerminationSignals,
        'timezone' => is_string($event->timezone) ? $event->timezone : $event->timezone?->getName(),
    ], app(Schedule::class)->events());
}

it('schedules the queue drainer every minute after every job-queuing task, with a short overlap lock', function () {
    $events = scheduledEvents();
    $commands = array_column($events, 'command');
    $drainIndex = array_search('app:drain-queue', $commands, true);

    expect($drainIndex)->not->toBeFalse()
        ->and($events[$drainIndex])->toMatchArray([
            'command' => 'app:drain-queue',
            'expression' => '* * * * *',
            'withoutOverlapping' => true,
            'expiresAt' => 5,
            'releaseOnTerminationSignals' => false,
        ])
        // DISC-37: only health:check (which queues nothing) runs after the
        // drain, so it sees the queue heartbeat processed in the same run.
        ->and(array_slice($commands, $drainIndex + 1))->toBe(['health:check'])
        ->and(array_search('health:queue-check-heartbeat', $commands, true))->toBeLessThan($drainIndex)
        ->and(array_search('health:schedule-check-heartbeat', $commands, true))->toBeLessThan($drainIndex);
});

it('leaves the existing scheduled tasks and their lock durations unchanged', function () {
    $events = scheduledEvents();

    // 6 + DB-002's 4 nightly retention tasks + DB-004's hourly cache prune + OPS-001's sitemap rebuild + BIZ-027's Steadfast check + the Meta Conversions API outbox sweeper + DISC-37's 3 System Health tasks + DISC-44's nightly log trim.
    expect($events)->toHaveCount(18);
    expect(array_slice($events, 0, 5))->toBe([
        ['command' => 'pathao:sync-statuses', 'expression' => '*/15 * * * *', 'withoutOverlapping' => true, 'expiresAt' => 30, 'releaseOnTerminationSignals' => false, 'timezone' => 'UTC'],
        ['command' => 'rewards:grant-due', 'expression' => '0 1 * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'releaseOnTerminationSignals' => false, 'timezone' => 'Asia/Dhaka'],
        ['command' => 'cart:remind-abandoned', 'expression' => '0 * * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'releaseOnTerminationSignals' => false, 'timezone' => 'UTC'],
        ['command' => 'pending-checkouts:prune', 'expression' => '0 2 * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'releaseOnTerminationSignals' => false, 'timezone' => 'Asia/Dhaka'],
        ['command' => 'newsletter:prune-attachments', 'expression' => '0 3 * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'releaseOnTerminationSignals' => false, 'timezone' => 'Asia/Dhaka'],
    ]);
});

/**
 * Hostinger loads the pcntl extension but disables pcntl_signal(); Laravel's
 * scheduler calls it for any event that releases its lock on termination
 * signals, which made every scheduled event fail before it started.
 */
it('registers no termination-signal handlers for any scheduled event', function () {
    $events = scheduledEvents();

    expect(array_column($events, 'releaseOnTerminationSignals'))->each->toBeFalse();
});

it('sends a database-queued mail and empties the queue when the worker runs', function () {
    config(['queue.default' => 'database']);
    $user = User::factory()->create(['email' => 'queued@example.com']);
    Event::fake([MessageSent::class]);
    Mail::to($user->email)->send(new WelcomeMail($user));

    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'default', 'attempts' => 0]);

    // The worker runs inside the suite's own process, so its default 128MB
    // limit would measure the whole suite's memory and exit with code 12.
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--stop-when-empty' => true, '--tries' => 3, '--memory' => 1024])
        ->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
    Event::assertDispatchedTimes(MessageSent::class, 1);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->getTo()[0]->getAddress() === 'queued@example.com');
});

it('does not touch jobs parked on another queue name', function () {
    config(['queue.default' => 'database']);
    $user = User::factory()->create(['email' => 'parked@example.com']);
    Event::fake([MessageSent::class]);
    Mail::to($user->email)->send(new WelcomeMail($user));
    DB::table('jobs')->update(['queue' => 'held-backlog']);

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--stop-when-empty' => true, '--tries' => 3, '--memory' => 1024])
        ->assertSuccessful();

    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'held-backlog', 'attempts' => 0, 'reserved_at' => null]);
    $this->assertDatabaseCount('failed_jobs', 0);
    Event::assertNotDispatched(MessageSent::class);
});
