<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Illuminate\Support\php_binary;

/**
 * Drains the `default` database queue on hosts where `queue:work`'s daemon
 * mode can't run: Hostinger shared hosting loads pcntl but disables
 * pcntl_signal(), which the daemon calls whenever the extension is loaded
 * (Worker::listenForSignals()), so it crashes before taking a job.
 *
 * Each job runs through the real `queue:work --once` command in its own
 * short-lived child process rather than in this one, because:
 * - `--once` never registers signal handlers (Worker::runNextJob());
 * - only `queue:work` writes failed jobs to `failed_jobs`, via a listener it
 *   registers every time it runs — calling it repeatedly in-process would
 *   record one failure several times, and bypassing it would record none;
 * - a child can be killed after --timeout seconds by Symfony Process, which
 *   polls instead of using SIGALRM, so a hung job (e.g. a stalled SMTP
 *   connection) is still cut off without pcntl.
 *
 * A killed (or crashed) child leaves its job reserved; the database queue
 * hands it out again once the connection's retry_after (90s) passes, counting
 * the dead run as an attempt, so --timeout must stay below retry_after. See
 * eligibleJobCount() for how such jobs are still picked up.
 */
#[Signature('app:drain-queue
    {--max-time=50 : Stop starting new jobs after this many seconds}
    {--timeout=45 : Kill a single job\'s worker process after this many seconds}')]
#[Description('Process every available job on the default database queue, one short-lived `queue:work --once` process per job, without needing pcntl signals.')]
class DrainQueueCommand extends Command
{
    private const CONNECTION = 'database';

    private const QUEUE = 'default';

    public function handle(): int
    {
        /** @var DatabaseQueue $queue */
        $queue = Queue::connection(self::CONNECTION);
        $startedAt = now();
        $maxSeconds = (int) $this->option('max-time');
        $workerRuns = 0;
        $timedOutRuns = 0;
        $eligibleJobs = $this->eligibleJobCount($queue);

        while ($eligibleJobs > 0 && $startedAt->diffInSeconds(now()) < $maxSeconds) {
            $workerRuns++;

            try {
                $result = Process::path(base_path())
                    ->timeout((int) $this->option('timeout'))
                    ->run($this->workerCommand());
            } catch (ProcessTimedOutException) {
                $timedOutRuns++;

                Log::error('Queue drain: a job exceeded the per-job timeout and its worker process was killed. It will be retried after the queue\'s retry_after.', [
                    'timeout_seconds' => (int) $this->option('timeout'),
                ]);

                $eligibleJobs = $this->eligibleJobCount($queue);

                continue;
            }

            if ($result->failed()) {
                Log::error('Queue drain: a worker process exited with an error; stopping this run.', [
                    'exit_code' => $result->exitCode(),
                    'error_output' => Str::limit(trim($result->errorOutput()), 1000),
                ]);

                return self::FAILURE;
            }

            // A worker that ran without making a job ineligible (another
            // process took the job, a lock blocked the pop, or the job
            // released itself straight back) would otherwise be started
            // again and again until max-time; the next minute's run picks
            // up whatever is still there.
            $previouslyEligible = $eligibleJobs;
            $eligibleJobs = $this->eligibleJobCount($queue);

            if ($eligibleJobs >= $previouslyEligible) {
                Log::warning('Queue drain: a worker process finished without reducing the number of eligible jobs; stopping this run.', [
                    'eligible_jobs' => $eligibleJobs,
                ]);

                break;
            }
        }

        $stillPending = $this->eligibleJobCount($queue);

        if ($workerRuns > 0) {
            Log::info('Queue drain finished.', [
                'worker_runs' => $workerRuns,
                'timed_out_runs' => $timedOutRuns,
                'still_pending' => $stillPending,
            ]);
        }

        $this->info("Ran {$workerRuns} worker process(es); {$timedOutRuns} timed out; {$stillPending} job(s) still pending.");

        return $timedOutRuns > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Jobs a worker would take right now: the same two conditions as
     * DatabaseQueue::getNextAvailableJob() — unreserved and due, or reserved
     * longer ago than retry_after (a worker that died mid-job). Counting only
     * the first, as pendingSize() does, would strand a dead worker's job
     * until some other job happened to arrive. Delayed jobs (retry backoff),
     * recently reserved jobs and jobs on any other queue are excluded, so
     * none of them keeps the loop running.
     */
    private function eligibleJobCount(DatabaseQueue $queue): int
    {
        $config = config('queue.connections.'.self::CONNECTION);
        $now = now();
        $reservationExpiredBefore = $now->copy()->subSeconds($config['retry_after'] ?? 60)->getTimestamp();

        return $queue->getDatabase()
            ->table($config['table'])
            ->where('queue', self::QUEUE)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNull('reserved_at')->where('available_at', '<=', $now->getTimestamp()))
                ->orWhere('reserved_at', '<=', $reservationExpiredBefore))
            ->count();
    }

    /**
     * One `queue:work --once` run against the default database queue, with
     * the same retry policy the scheduled daemon used. `--sleep=0` stops the
     * child sleeping if another process took the job between our pending
     * check and its pop.
     *
     * @return list<string>
     */
    private function workerCommand(): array
    {
        return [
            php_binary(),
            base_path('artisan'),
            'queue:work',
            self::CONNECTION,
            '--queue='.self::QUEUE,
            '--once',
            '--sleep=0',
            '--tries=3',
            '--backoff=30',
        ];
    }
}
