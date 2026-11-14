<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\JsonFileHealthResultStore;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;

/**
 * DISC-37: Admin > System Health, the results of the spatie/laravel-health
 * checks registered in AppServiceProvider::configureHealthChecks(). Mother
 * admin only (`admin.mother` on the routes): the results reveal the server's
 * weak points. The package's own result pages are never registered.
 */
class SystemHealthController extends Controller
{
    public function index(ResultStore $resultStore): Response
    {
        $results = $this->resultsExist() ? $resultStore->latestResults() : null;

        return Inertia::render('admin/system-health/index', [
            'lastRanAt' => $results !== null ? CarbonImmutable::instance($results->finishedAt)->toIso8601String() : null,
            // Only the label, status and short texts: a check's meta can carry
            // an exception message, which this page has no need to show.
            'checks' => $results === null ? [] : $results->storedCheckResults
                ->map(fn (StoredCheckResult $check): array => [
                    'name' => $check->name,
                    'label' => $check->label,
                    'status' => $check->status,
                    'summary' => $check->shortSummary,
                    'message' => (string) $check->notificationMessage,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Run every check now and store the results. No email: the mother admin
     * is looking at the page; the every-minute scheduled run still alerts.
     */
    public function run(): RedirectResponse
    {
        Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);

        return back()->with('success', 'Health checks run.');
    }

    /**
     * Before the first run there is no results file, and the package's
     * reader would report() the missing file as an error on every visit.
     */
    private function resultsExist(): bool
    {
        $store = config('health.result_stores.'.JsonFileHealthResultStore::class);

        return is_array($store) && Storage::disk($store['disk'])->exists($store['path']);
    }
}
