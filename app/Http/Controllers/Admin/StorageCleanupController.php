<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StorageCleanupService;
use App\Support\PublicFileReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin › Storage Clean-up: the `storage:audit` command as a page. Shows the
 * uploaded files nothing uses any more, moves them aside into a restorable
 * batch, puts a batch back, and deletes a batch once it is 30 days old
 * (StorageCleanupService does the work for both). Mother admin only, like
 * Database Backup and Error Log: it moves files the whole site serves.
 */
class StorageCleanupController extends Controller
{
    public function index(StorageCleanupService $cleanup): Response
    {
        return Inertia::render('admin/storage-cleanup/index', [
            'report' => $cleanup->report(),
            'batches' => $cleanup->batches(),
            'graceDays' => PublicFileReferences::UPLOAD_DIRECTORIES,
            'purgeAfterDays' => StorageCleanupService::PURGE_AFTER_DAYS,
        ]);
    }

    public function quarantine(Request $request, StorageCleanupService $cleanup): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        $result = $cleanup->quarantine();

        if ($result['batch'] === null) {
            return back()->with('info', 'There are no unused files to move.');
        }

        $this->audit('storage.quarantined', "Moved {$result['moved']} unused files aside (batch {$result['batch']}).", $result);

        return back()->with(
            $result['failed'] === 0 ? 'success' : 'error',
            "Moved {$result['moved']} unused ".($result['moved'] === 1 ? 'file' : 'files')." aside (batch {$result['batch']})."
                .($result['failed'] > 0 ? " {$result['failed']} could not be moved and were left in place." : ' Check the site; you can put them back any time before the batch is deleted.'),
        );
    }

    public function restore(Request $request, StorageCleanupService $cleanup, string $batch): RedirectResponse
    {
        $result = $cleanup->restore($batch);

        abort_if($result === null, 404);

        $this->audit('storage.restored', "Put back {$result['restored']} files from batch {$batch}.", ['batch' => $batch] + $result);

        return back()->with('success', "Put back {$result['restored']} ".($result['restored'] === 1 ? 'file' : 'files')." from batch {$batch}."
            .($result['skipped'] > 0 ? " {$result['skipped']} skipped: a file with the same name already exists, or it was missing." : ''));
    }

    public function destroy(StorageCleanupService $cleanup, string $batch): RedirectResponse
    {
        $deleted = $cleanup->deleteBatch($batch);

        abort_if($deleted === null, 404);

        if ($deleted === false) {
            return back()->with('error', 'This batch can only be deleted once it is '.StorageCleanupService::PURGE_AFTER_DAYS.' days old.');
        }

        $this->audit('storage.purged', "Permanently deleted quarantine batch {$batch}.", ['batch' => $batch]);

        return back()->with('success', "Permanently deleted batch {$batch}.");
    }

    /** @param  array<string, mixed>  $properties */
    private function audit(string $event, string $description, array $properties): void
    {
        activity('admin')
            ->causedBy(Auth::guard('admin')->user())
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
