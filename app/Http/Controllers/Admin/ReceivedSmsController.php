<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReceivedSms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivedSmsController extends Controller
{
    /**
     * Lightweight poll endpoint for the admin layout toast notifications.
     * Returns SMS records with id > after, capped at 10.
     * Route: GET /admin/sms/poll
     */
    public function poll(Request $request): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $sms = ReceivedSms::where('id', '>', $after)
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'sender', 'content', 'parsed_transaction_id', 'parsed_amount', 'received_at']);

        return response()->json(['sms' => $sms]);
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
}
