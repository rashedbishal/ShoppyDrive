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
 * @return list<array{command: string, expression: string, withoutOverlapping: bool, expiresAt: int, timezone: string|null}>
 */
function scheduledEvents(): array
{
    return array_map(fn (ScheduledEvent $event): array => [
        'command' => Str::after($event->command, "'artisan' "),
        'expression' => $event->expression,
        'withoutOverlapping' => $event->withoutOverlapping,
        'expiresAt' => $event->expiresAt,
        'timezone' => is_string($event->timezone) ? $event->timezone : $event->timezone?->getName(),
    ], app(Schedule::class)->events());
}

it('schedules a database queue worker every minute as the last task, with a short overlap lock', function () {
    $events = scheduledEvents();

    expect(end($events))->toMatchArray([
        'command' => 'queue:work database --queue=default --stop-when-empty --max-time=50 --tries=3 --backoff=30 --timeout=45',
        'expression' => '* * * * *',
        'withoutOverlapping' => true,
        'expiresAt' => 5,
    ]);
});

it('leaves the existing scheduled tasks and their lock durations unchanged', function () {
    $events = scheduledEvents();

    expect($events)->toHaveCount(4);
    expect(array_slice($events, 0, 3))->toBe([
        ['command' => 'pathao:sync-statuses', 'expression' => '*/15 * * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'timezone' => 'UTC'],
        ['command' => 'rewards:grant-due', 'expression' => '0 1 * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'timezone' => 'Asia/Dhaka'],
        ['command' => 'cart:remind-abandoned', 'expression' => '0 * * * *', 'withoutOverlapping' => true, 'expiresAt' => 1440, 'timezone' => 'UTC'],
    ]);
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
