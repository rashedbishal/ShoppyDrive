<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Support\AdminListUrl;
use App\Support\MotherPasswordGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditTrailController extends Controller
{
    /**
     * `admin` covers every create/update/delete/login/logout captured on
     * admin-managed records; `orders` covers the two customer-facing events
     * (order placed, order status changed) — see `Order::getActivitylogOptions()`.
     *
     * @var array<string, string>
     */
    private const LOG_NAME_LABELS = [
        'admin' => 'Admin Activity',
        'orders' => 'Order Activity',
    ];

    public function index(Request $request): Response|RedirectResponse
    {
        $logName = $request->string('log_name')->value();
        $logName = array_key_exists($logName, self::LOG_NAME_LABELS) ? $logName : null;

        $activities = Activity::query()
            ->with('causer')
            ->when($logName, fn ($query) => $query->inLog($logName))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'log_name_label' => self::LOG_NAME_LABELS[$activity->log_name] ?? $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'causer' => $this->describeCauser($activity),
                'subject_type' => $activity->subject_type !== null ? class_basename($activity->subject_type) : null,
                'created_at' => $activity->created_at,
            ]);

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($activities, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        $currentAdmin = Auth::guard('admin')->user();

        return Inertia::render('admin/audit-trail/index', [
            'activities' => $activities,
            'filters' => ['log_name' => $logName],
            'logNameOptions' => self::LOG_NAME_LABELS,
            'canClear' => $currentAdmin->is_mother,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $currentAdmin = Auth::guard('admin')->user();

        if (! $currentAdmin->is_mother) {
            abort(403, 'Only the mother admin can clear the audit trail.');
        }

        $request->validate(['password' => ['required', 'string']]);

        // DB-001: same mother-password brute-force lock as permanent order deletion.
        if (MotherPasswordGate::isLocked($currentAdmin)) {
            return back()->withErrors(['password' => MotherPasswordGate::lockedMessage($currentAdmin)]);
        }

        if (! MotherPasswordGate::verify($currentAdmin, (string) $request->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        Activity::query()->delete();

        activity('admin')
            ->causedBy($currentAdmin)
            ->event('cleared')
            ->log("Admin {$currentAdmin->name} ({$currentAdmin->email}) cleared the audit trail.");

        return redirect()->route('admin.audit-trail.index')->with('success', 'Audit trail cleared.');
    }

    /**
     * @return array{name: string, type: string}
     */
    private function describeCauser(Activity $activity): array
    {
        $causer = $activity->causer;

        if ($causer instanceof Admin) {
            return ['name' => $causer->name, 'type' => 'Admin'];
        }

        if ($causer instanceof User) {
            return ['name' => $causer->name, 'type' => 'Customer'];
        }

        return ['name' => 'System', 'type' => 'System'];
    }
}
