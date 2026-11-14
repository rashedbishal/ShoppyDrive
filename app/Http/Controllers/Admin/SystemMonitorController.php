<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CronHealth;
use App\Support\MotherPasswordGate;
use App\Support\ParkedJobs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OPS-002: Admin > System Monitor — whether cron and the queue are running,
 * readable by anyone who can already see Failed Jobs (the same section the
 * dashboard's queue banner is gated by). Parked jobs (2026-11-14) are listed
 * there too; deleting them is mother-admin only.
 */
class SystemMonitorController extends Controller
{
    public function index(): Response
    {
        $isMother = (bool) Auth::guard('admin')->user()->is_mother;

        return Inertia::render('admin/monitor/index', [
            'monitor' => CronHealth::snapshot(),
            // The order number and masked recipient: mother admin only.
            'parkedJobs' => ParkedJobs::list(withSummary: $isMother),
            'canDeleteParkedJobs' => $isMother,
        ]);
    }

    /**
     * Deletes the parked jobs the mother admin was shown, after her password
     * (same 5-wrong-passwords lock as clearing the audit trail). Only ids that
     * are still parked are removed (ParkedJobs::delete); every deletion is
     * written to the Audit Trail with each job's queue and class name.
     */
    public function destroyParked(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1', 'max:'.ParkedJobs::LIST_LIMIT],
            'ids.*' => ['integer', 'min:1'],
        ]);

        if (MotherPasswordGate::isLocked($admin)) {
            return back()->withErrors(['password' => MotherPasswordGate::lockedMessage($admin)]);
        }

        if (! MotherPasswordGate::verify($admin, (string) $validated['password'])) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $deleted = ParkedJobs::delete(array_values(array_unique(array_map('intval', $validated['ids']))));

        if ($deleted === []) {
            return back()->with('success', 'Nothing was deleted: those jobs are no longer parked.');
        }

        activity('admin')
            ->causedBy($admin)
            ->event('deleted')
            ->withProperties(['parked_jobs' => $deleted])
            ->log(sprintf('Admin %s deleted %d parked background job%s.', $admin->name, count($deleted), count($deleted) === 1 ? '' : 's'));

        return back()->with('success', sprintf('Deleted %d parked job%s.', count($deleted), count($deleted) === 1 ? '' : 's'));
    }
}
