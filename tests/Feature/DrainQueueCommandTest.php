<?php

declare(strict_types=1);

use App\Console\Commands\DrainQueueCommand;
use App\Mail\LowStockAlertMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;
use function Pest\Laravel\travel;

function queueWelcomeMailFor(string $email): void
{
    $user = User::factory()->create(['email' => $email]);
    Mail::to($user->email)->send(new WelcomeMail($user));
}

/**
 * Stands in for each `queue:work --once` child process by running that same
 * command in-process against the test database, so the jobs really are
 * popped, run, retried or failed by Laravel's worker.
 */
function fakeWorkerProcessesRunInProcess(?Closure $afterEachRun = null): void
{
    Process::fake(function () use ($afterEachRun) {
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0, '--tries' => 3]);

        if ($afterEachRun) {
            $afterEachRun();
        }

        return Process::result();
    });
}

// ── OPS-002: a stalled cron creates no failed_jobs rows, so a heartbeat is the only way to see it ──

it('records a heartbeat every time it runs, even when there is nothing to drain', function () {
    config(['queue.default' => 'database']);
    Process::fake();

    expect(Cache::get(DrainQueueCommand::LAST_RUN_CACHE_KEY))->toBeNull();

    $this->artisan('app:drain-queue')->assertSuccessful();

    expect(Cache::get(DrainQueueCommand::LAST_RUN_CACHE_KEY))->not->toBeNull();
});

it('reports the oldest pending job\'s age, or null when the default queue is empty', function () {
    config(['queue.default' => 'database']);

    expect(DrainQueueCommand::oldestPendingJobAgeSeconds())->toBeNull();

    queueWelcomeMailFor('oldest@example.com');
    DB::table('jobs')->update(['created_at' => now()->subMinutes(20)->getTimestamp()]);

    expect(DrainQueueCommand::oldestPendingJobAgeSeconds())->toBeGreaterThanOrEqual(20 * 60);
});

it('processes every available default-queue job in one run, one worker process per job', function () {
    config(['queue.default' => 'database']);
    Event::fake([MessageSent::class]);
    queueWelcomeMailFor('first@example.com');
    queueWelcomeMailFor('second@example.com');
    queueWelcomeMailFor('third@example.com');
    fakeWorkerProcessesRunInProcess();

    $this->artisan('app:drain-queue')->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
    Event::assertDispatchedTimes(MessageSent::class, 3);
    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === [
        php_binary(), base_path('artisan'), 'queue:work', 'database', '--queue=default,newsletter', '--once', '--sleep=0', '--tries=3', '--backoff=300',
    ] && $process->timeout === 45, 3);
});

it('never processes jobs parked on another queue name', function () {
    config(['queue.default' => 'database']);
    Event::fake([MessageSent::class]);
    queueWelcomeMailFor('parked@example.com');
    DB::table('jobs')->update(['queue' => 'held-backlog']);
    queueWelcomeMailFor('fresh@example.com');
    fakeWorkerProcessesRunInProcess();

    $this->artisan('app:drain-queue')->assertSuccessful();

    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'held-backlog', 'attempts' => 0, 'reserved_at' => null]);
    Process::assertRanTimes(fn (): bool => true, 1);
    Event::assertDispatchedTimes(MessageSent::class, 1);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->message->getTo()[0]->getAddress() === 'fresh@example.com');
});

it('exits without starting a worker when no default-queue job is available yet', function () {
    config(['queue.default' => 'database']);
    queueWelcomeMailFor('backing-off@example.com');
    DB::table('jobs')->update(['available_at' => now()->addMinutes(5)->getTimestamp()]);
    Process::fake();

    $this->artisan('app:drain-queue')->assertSuccessful();

    Process::assertNothingRan();
    $this->assertDatabaseHas('jobs', ['queue' => 'default', 'attempts' => 0, 'reserved_at' => null]);
});

it('stops starting new jobs once the run has lasted 50 seconds', function () {
    config(['queue.default' => 'database']);
    queueWelcomeMailFor('one@example.com');
    queueWelcomeMailFor('two@example.com');
    queueWelcomeMailFor('three@example.com');
    fakeWorkerProcessesRunInProcess(fn () => travel(30)->seconds());

    $this->artisan('app:drain-queue')->assertSuccessful();

    Process::assertRanTimes(fn (): bool => true, 2);
    $this->assertDatabaseCount('jobs', 1);
});

it('retries a job whose worker died once its reservation expires, and records it as failed after three tries', function () {
    config(['queue.default' => 'database']);
    Event::fake([MessageSent::class]);
    queueWelcomeMailFor('dying@example.com');
    $workerRuns = 0;
    Process::fake(function () use (&$workerRuns) {
        $workerRuns++;

        if ($workerRuns <= 3) {
            DB::table('jobs')->update(['reserved_at' => now()->getTimestamp(), 'attempts' => DB::raw('attempts + 1')]);

            return Process::result(exitCode: 137);
        }

        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0, '--tries' => 3]);

        return Process::result();
    });
    Log::spy();

    foreach (range(1, 3) as $deadRun) {
        $this->artisan('app:drain-queue')->assertFailed();
        $this->artisan('app:drain-queue')->assertSuccessful();
        travel(91)->seconds();
    }
    $this->artisan('app:drain-queue')->assertSuccessful();

    expect($workerRuns)->toBe(4);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'exited with an error'))->times(3);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
    Event::assertNotDispatched(MessageSent::class);
});

it('leaves a job reserved within the last 90 seconds alone and starts no worker for it', function () {
    config(['queue.default' => 'database']);
    queueWelcomeMailFor('in-flight@example.com');
    $reservedAt = now()->subSeconds(30)->getTimestamp();
    DB::table('jobs')->update(['reserved_at' => $reservedAt, 'attempts' => 1]);
    Process::fake();

    $this->artisan('app:drain-queue')->assertSuccessful();

    Process::assertNothingRan();
    $this->assertDatabaseHas('jobs', ['queue' => 'default', 'reserved_at' => $reservedAt, 'attempts' => 1]);
});

it('logs a warning and stops after one worker process that leaves the job eligible', function () {
    config(['queue.default' => 'database']);
    queueWelcomeMailFor('stuck@example.com');
    $jobBefore = (array) DB::table('jobs')->first();
    Process::fake(fn () => Process::result());
    Log::spy();

    $this->artisan('app:drain-queue')->assertSuccessful();

    Process::assertRanTimes(fn (): bool => true, 1);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'without reducing the number of eligible jobs')
        && $context === ['eligible_jobs' => 1]);
    expect((array) DB::table('jobs')->first())->toBe($jobBefore);
});

it('logs and stops the run when a worker process exits with an error', function () {
    config(['queue.default' => 'database']);
    queueWelcomeMailFor('first@example.com');
    queueWelcomeMailFor('second@example.com');
    Process::fake(fn () => Process::result(errorOutput: 'database unavailable', exitCode: 1));
    Log::shouldReceive('error')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'exited with an error')
        && $context === ['exit_code' => 1, 'error_output' => 'database unavailable']);

    $this->artisan('app:drain-queue')->assertFailed();

    Process::assertRanTimes(fn (): bool => true, 1);
    $this->assertDatabaseCount('jobs', 2);
});

/**
 * Hostinger loads the pcntl extension but disables its signal functions.
 * This reproduces that for real: an extra ini directory appended through
 * PHP_INI_SCAN_DIR (inherited by every child process) disables them, and a
 * real `app:drain-queue` process, with its real `queue:work --once`
 * children, drains a job from a throwaway SQLite database.
 */
it('drains a real queued job in child processes with the pcntl signal functions disabled', function () {
    if (app()->configurationIsCached()) {
        $this->markTestSkipped('Child processes would ignore the environment overrides while the configuration is cached.');
    }

    $workDirectory = sys_get_temp_dir().'/drain-queue-'.bin2hex(random_bytes(6));
    mkdir($workDirectory.'/ini', recursive: true);
    file_put_contents($workDirectory.'/ini/zz-disable-pcntl-signals.ini', "disable_functions=pcntl_signal,pcntl_async_signals,pcntl_alarm\n");
    $databaseFile = $workDirectory.'/queue.sqlite';
    touch($databaseFile);
    $childProcess = Process::path(base_path())->timeout(60)->env([
        'PHP_INI_SCAN_DIR' => PATH_SEPARATOR.$workDirectory.'/ini',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $databaseFile,
        'DB_URL' => '',
        'MAIL_MAILER' => 'array',
    ]);

    try {
        expect($childProcess->run([php_binary(), '-r', 'echo function_exists("pcntl_signal") ? "available" : "disabled";'])->output())->toBe('disabled');
        expect(trim($childProcess->run([php_binary(), 'artisan', 'config:show', 'database.connections.sqlite.database'])->output()))->toContain($databaseFile);
        expect($childProcess->run([php_binary(), 'artisan', 'migrate', '--database=sqlite', '--force'])->successful())->toBeTrue();
        config([
            'database.connections.drain_probe' => ['driver' => 'sqlite', 'database' => $databaseFile, 'prefix' => '', 'foreign_key_constraints' => true],
            'queue.connections.drain_probe' => ['driver' => 'database', 'connection' => 'drain_probe', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90],
        ]);
        Mail::to('probe@example.com')->queue((new LowStockAlertMail('Probe product', 'SKU-PROBE', 2, 5))->onConnection('drain_probe'));
        expect(DB::connection('drain_probe')->table('jobs')->count())->toBe(1);

        $drain = $childProcess->run([php_binary(), 'artisan', 'app:drain-queue']);

        expect($drain->exitCode())->toBe(0, $drain->errorOutput());
        expect($drain->output())->toContain('Ran 1 worker process(es); 0 timed out; 0 job(s) still pending.');
        expect(DB::connection('drain_probe')->table('jobs')->count())->toBe(0);
        expect(DB::connection('drain_probe')->table('failed_jobs')->count())->toBe(0);
    } finally {
        DB::purge('drain_probe');
        array_map('unlink', glob($workDirectory.'/ini/*') ?: []);
        rmdir($workDirectory.'/ini');
        array_map('unlink', glob($workDirectory.'/*') ?: []);
        rmdir($workDirectory);
    }
});
