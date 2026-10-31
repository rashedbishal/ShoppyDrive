<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\CourierVerificationRefusedException;
use App\Http\Controllers\Controller;
use App\Models\CourierVerificationReadiness;
use App\Services\CourierVerificationReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PAY-RETURN-001: the mother admin's operational readiness attestation
 * (route: admin.mother; the POST also throttle:30,15 and session blocking).
 * It is an audited attestation, not a proof that old processes are gone.
 */
class CourierVerificationReadinessController extends Controller
{
    public function index(CourierVerificationReadinessService $readiness): Response
    {
        $live = $readiness->liveFingerprint();

        return Inertia::render('admin/courier-readiness/index', [
            'live' => [
                'fingerprint' => $live['fingerprint'],
                'file_count' => $live['count'],
                'deployment_marker' => $readiness->deploymentMarker(),
                'opcache' => $readiness->opcacheProbe(),
                'scheduler_marker' => $readiness->schedulerMarker(),
                'max_execution_time' => $readiness->maxExecutionTime(),
            ],
            'current' => $readiness->current()?->only(['id', 'created_at', 'admin_name', 'deploy_sha', 'deploy_run_id']),
            'recent' => CourierVerificationReadiness::latest('id')->limit(10)
                ->get(['id', 'status', 'refusal_reasons', 'deploy_run_id', 'deploy_sha', 'admin_name', 'created_at']),
        ]);
    }

    public function store(Request $request, CourierVerificationReadinessService $readiness): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'deploy_run_id' => ['required', 'string', 'max:40'],
            'deploy_sha' => ['required', 'string', 'max:40'],
            'deploy_run_started_at' => ['required', 'date'],
            'deploy_run_completed_at' => ['required', 'date'],
            'local_fingerprint' => ['required', 'string', 'max:64'],
            'web_restarted_at' => ['required', 'date'],
            'process_list' => ['required', 'string', 'max:20000'],
            'confirm_latest_deploy' => ['accepted'],
            'confirm_web_restarted' => ['accepted'],
            'confirm_no_old_processes' => ['accepted'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $record = $readiness->attest(Auth::guard('admin')->user(), $request, $validated);
        } catch (CourierVerificationRefusedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return $record->status === CourierVerificationReadiness::STATUS_ACCEPTED
            ? back()->with('success', 'Readiness accepted. Consignments can now be verified for this release.')
            : back()->with('error', 'Readiness refused: '.implode(' ', $record->refusal_reasons ?? []));
    }
}
