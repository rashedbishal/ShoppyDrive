<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReceivedSms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ReceivedSmsController extends Controller
{
    /**
     * FSA-07: an SMS unmatched for longer than this is past any realistic
     * time a genuine customer takes to type a TrxID into the payment form,
     * so it's worth an admin's attention — a reconciliation signal, not a
     * hard cutoff (the SMS and its PendingCheckout stay usable long after
     * this, up to the checkout's own 2-hour window). Comfortably shorter
     * than that 2-hour window, so there's still time to help the customer.
     */
    private const UNMATCHED_STALE_MINUTES = 30;

    /**
     * OPS-005: the Android forwarder phone (a single point of failure) can
     * go silent — dead battery, no signal, app crashed — without ever
     * producing an unmatched SMS to count, so UNMATCHED_STALE_MINUTES above
     * stays at 0 regardless and looks healthy. Six hours is deliberately
     * loose: a real store can plausibly see zero bKash/Nagad payments for
     * stretches of a slow day/night, and this only needs to catch "the
     * phone has clearly been off for a while," not flag every quiet hour.
     */
    private const FORWARDER_SILENT_MINUTES = 360;

    /**
     * Lightweight poll endpoint for the admin layout toast notifications.
     * Returns SMS records with id > after, capped at 10, plus a running
     * count of stale unmatched SMS (FSA-07) and how long it's been since
     * any SMS arrived at all (OPS-005) for the sidebar badge — both
     * piggyback on the same poll already running; no new route,
     * table, or dashboard.
     * Route: GET /admin/sms/poll
     */
    public function poll(Request $request): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $sms = ReceivedSms::where('id', '>', $after)
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'sender', 'content', 'parsed_transaction_id', 'parsed_amount', 'received_at']);

        $unmatchedCount = ReceivedSms::where('is_matched', false)
            ->where('received_at', '<=', now()->subMinutes(self::UNMATCHED_STALE_MINUTES))
            ->count();

        $lastReceivedAt = ReceivedSms::max('received_at');

        return response()->json([
            'sms' => $sms,
            'unmatched_count' => $unmatchedCount,
            'forwarder_seems_down' => $lastReceivedAt === null
                ? false // never configured/used yet — not this phone's problem to report
                : now()->diffInMinutes($lastReceivedAt, true) >= self::FORWARDER_SILENT_MINUTES,
        ]);
    }

    public function index(Request $request): Response
    {
        $sms = ReceivedSms::with('matchedOrder:id,order_number')
            ->when($request->matched === 'yes', fn ($q) => $q->where('is_matched', true))
            ->when($request->matched === 'no', fn ($q) => $q->where('is_matched', false))
            ->when($request->search, fn ($q) => $q->where(fn ($match) => $match
                ->where('parsed_transaction_id', 'like', "%{$request->search}%")
                ->orWhere('sender', 'like', "%{$request->search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/received-sms/index', [
            'sms' => $sms,
            'filters' => $request->only(['search', 'matched']),
        ]);
    }

    public function destroy(ReceivedSms $receivedSms, Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $currentAdmin = Auth::guard('admin')->user();

        if (! Hash::check($request->password, $currentAdmin->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $receivedSms->delete();

        return back()->with('success', 'SMS deleted successfully.');
    }
}
