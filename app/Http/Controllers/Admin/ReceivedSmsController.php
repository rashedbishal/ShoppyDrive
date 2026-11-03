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
     * Lightweight poll endpoint for the admin layout toast notifications.
     * Returns SMS records with id > after, capped at 10, plus a running
     * count of stale unmatched SMS (FSA-07) for the sidebar badge — both
     * piggyback on the same 5-second poll already running; no new route,
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

        return response()->json(['sms' => $sms, 'unmatched_count' => $unmatchedCount]);
    }

    public function index(Request $request): Response
    {
        $sms = ReceivedSms::with('matchedOrder:id,order_number')
            ->when($request->matched === 'yes', fn ($q) => $q->where('is_matched', true))
            ->when($request->matched === 'no', fn ($q) => $q->where('is_matched', false))
            ->when($request->search, fn ($q) => $q->where('parsed_transaction_id', 'like', "%{$request->search}%")
                ->orWhere('sender', 'like', "%{$request->search}%"))
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
