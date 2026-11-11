<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminListUrl;
use App\Support\FailedJobSummary;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MED-03: jobs the queue gave up on. Laravel records them, but nothing else
 * read that table, so a customer email could fail with nobody knowing.
 */
class FailedJobController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $failedJobs = $this->failedJobs()
            ->orderByDesc('failed_at')
            ->paginate(20)
            ->through(fn (object $failedJob): array => FailedJobSummary::from($failedJob));

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($failedJobs, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        $admin = Auth::guard('admin')->user();

        return Inertia::render('admin/failed-jobs/index', [
            'failedJobs' => $failedJobs,
            'canRetry' => $admin->canAccess('failed_jobs', 'update'),
            'canDelete' => $admin->canAccess('failed_jobs', 'delete'),
        ]);
    }

    /**
     * The row lock and Laravel's own retry, which re-queues and forgets the
     * record, share one transaction, so a double submit re-queues it once.
     */
    public function retry(Request $request, string $uuid): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        $outcome = DB::transaction(function () use ($uuid): string {
            $failedJob = $this->failedJobs()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($failedJob === null) {
                return 'missing';
            }

            if (! FailedJobSummary::canRetry($failedJob)) {
                return 'refused';
            }

            Artisan::call('queue:retry', ['id' => [$uuid]]);
            $this->audit('failed_job_retried', 'retried', $failedJob);

            return 'retried';
        });

        return match ($outcome) {
            'missing' => back()->with('error', 'That job is no longer in the failed list.'),
            'refused' => back()->with('error', 'This type of job is not safe to retry from here.'),
            default => back()->with('success', 'Job queued to run again.'),
        };
    }

    /**
     * OPS-003: retrying one row at a time doesn't scale once a mail outage
     * fails a whole backlog at once. Retries every currently-retryable
     * failed job, one at a time, under the same lock+check each single
     * retry uses — a job that becomes unsafe to retry between the listing
     * and this request (e.g. already retried by someone else) is skipped,
     * not retried twice.
     */
    public function retryAll(Request $request): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        $uuids = $this->failedJobs()->pluck('uuid');
        $retried = 0;

        foreach ($uuids as $uuid) {
            $outcome = DB::transaction(function () use ($uuid): bool {
                $failedJob = $this->failedJobs()->where('uuid', $uuid)->lockForUpdate()->first();

                if ($failedJob === null || ! FailedJobSummary::canRetry($failedJob)) {
                    return false;
                }

                Artisan::call('queue:retry', ['id' => [$uuid]]);
                $this->audit('failed_job_retried', 'retried', $failedJob);

                return true;
            });

            if ($outcome) {
                $retried++;
            }
        }

        if ($retried === 0) {
            return back()->with('error', 'No retryable failed jobs were found.');
        }

        return back()->with('success', "{$retried} job(s) queued to run again.");
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        $deleted = DB::transaction(function () use ($uuid): bool {
            $failedJob = $this->failedJobs()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($failedJob === null) {
                return false;
            }

            $this->failedJobs()->where('uuid', $uuid)->delete();
            $this->audit('failed_job_deleted', 'deleted', $failedJob);

            return true;
        });

        return $deleted
            ? back()->with('success', 'Failed job record deleted.')
            : back()->with('error', 'That job is no longer in the failed list.');
    }

    private function failedJobs(): Builder
    {
        return DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'));
    }

    private function audit(string $event, string $verb, object $failedJob): void
    {
        $summary = FailedJobSummary::from($failedJob);

        activity('admin')
            ->causedBy(Auth::guard('admin')->user())
            ->event($event)
            ->withProperties(['uuid' => $summary['uuid'], 'job' => $summary['job'], 'queue' => $summary['queue']])
            ->log("Failed job {$verb}: {$summary['job']} ({$summary['uuid']})");
    }
}
