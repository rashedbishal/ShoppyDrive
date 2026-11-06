<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\DatabaseBackupExporter;
use App\Services\DatabaseBackupRestorer;
use App\Support\MotherPasswordGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OPS-008: mother admin only, on the route and here. A backup holds every
 * customer's details, password hashes and the courier credentials stored in
 * settings, and a restore can change any of them. Both need her password.
 */
class DatabaseBackupController extends Controller
{
    private const string PENDING_SESSION_KEY = 'database_restore.pending';

    private const string RESULT_SESSION_KEY = 'database_restore.result';

    public const string UPLOAD_DIRECTORY = 'database-restores';

    private const int PENDING_MINUTES = 30;

    private const int MAX_UPLOAD_KILOBYTES = 102400;

    public function index(Request $request): Response
    {
        $pending = $this->pending($request);

        return Inertia::render('admin/database-backup/index', [
            'csrfToken' => csrf_token(),
            'maxUploadMegabytes' => (int) (self::MAX_UPLOAD_KILOBYTES / 1024),
            'pendingReport' => $pending['report'] ?? null,
            'restoreResult' => $request->session()->pull(self::RESULT_SESSION_KEY),
        ]);
    }

    public function download(Request $request, DatabaseBackupExporter $exporter): StreamedResponse|RedirectResponse
    {
        if (($failure = $this->passwordFailure($request)) !== null) {
            return $failure;
        }

        activity('admin')
            ->causedBy($this->motherAdmin())
            ->event('downloaded')
            ->log('Admin '.$this->motherAdmin()->name.' downloaded a full database backup.');

        @set_time_limit(0);

        return response()->streamDownload(
            function () use ($exporter): void {
                $exporter->export(function (string $line): void {
                    echo $line;
                });
            },
            'shoppydrive-backup-'.now('Asia/Dhaka')->format('Y-m-d-His').'.sql',
            ['Content-Type' => 'application/sql', 'Cache-Control' => 'no-store', 'X-Accel-Buffering' => 'no'],
        );
    }

    /**
     * Checks an uploaded backup. Nothing it finds stays in the database.
     */
    public function inspect(Request $request, DatabaseBackupRestorer $restorer): RedirectResponse
    {
        $request->validate([
            'backup' => ['required', 'file', 'extensions:sql', 'max:'.self::MAX_UPLOAD_KILOBYTES],
        ], ['backup.extensions' => 'Choose a .sql backup file.']);

        $this->forgetPending($request);

        $path = $request->file('backup')->storeAs(self::UPLOAD_DIRECTORY, Str::uuid().'.sql', 'local');

        @set_time_limit(0);

        $request->session()->put(self::PENDING_SESSION_KEY, [
            'path' => $path,
            'admin_id' => $this->motherAdmin()->id,
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
            'report' => $restorer->rehearse(Storage::disk('local')->path($path)),
        ]);

        return redirect()->route('admin.database-backup.index');
    }

    public function apply(Request $request, DatabaseBackupRestorer $restorer): RedirectResponse
    {
        if (($failure = $this->passwordFailure($request)) !== null) {
            return $failure;
        }

        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('admin.database-backup.index')
                ->with('error', 'That check has expired. Upload the backup file again.');
        }

        @set_time_limit(0);

        // The database may have moved on since the check, so it runs in full again.
        $report = $restorer->apply(Storage::disk('local')->path($pending['path']));
        $this->forgetPending($request);

        $request->session()->put(self::RESULT_SESSION_KEY, $report);

        if ($report['committed']) {
            activity('admin')
                ->causedBy($this->motherAdmin())
                ->event('restored')
                ->log('Admin '.$this->motherAdmin()->name.' restored a database backup ('.$report['rows'].' rows checked).');
        }

        return redirect()->route('admin.database-backup.index');
    }

    public function discard(Request $request): RedirectResponse
    {
        $this->forgetPending($request);

        return redirect()->route('admin.database-backup.index');
    }

    private function motherAdmin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }

    private function passwordFailure(Request $request): ?RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $admin = $this->motherAdmin();

        if (MotherPasswordGate::isLocked($admin)) {
            return back()->withErrors(['password' => MotherPasswordGate::lockedMessage($admin)]);
        }

        if (! MotherPasswordGate::verify($admin, (string) $request->input('password'))) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        return null;
    }

    /**
     * @return array{path: string, admin_id: int, expires_at: int, report: array<string, mixed>}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if ($pending === null) {
            return null;
        }

        $isUsable = $pending['admin_id'] === $this->motherAdmin()->id
            && $pending['expires_at'] > now()->timestamp
            && Storage::disk('local')->exists($pending['path']);

        if (! $isUsable) {
            $this->forgetPending($request);

            return null;
        }

        return $pending;
    }

    private function forgetPending(Request $request): void
    {
        $pending = $request->session()->pull(self::PENDING_SESSION_KEY);

        if ($pending !== null) {
            Storage::disk('local')->delete($pending['path']);
        }
    }
}
