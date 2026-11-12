<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BlockedIpController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blocked-ips/index', [
            'blockedIps' => BlockedIp::with('blockedByAdmin:id,name')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip_address' => ['required', 'ip', 'unique:blocked_ips,ip_address'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(fn () => BlockedIp::create($data + ['blocked_by' => Auth::guard('admin')->id()]));
        BlockedIp::flushCache();

        return back()->with('success', 'IP address blocked.');
    }

    public function destroy(BlockedIp $blockedIp): RedirectResponse
    {
        DB::transaction(fn () => $blockedIp->delete());
        BlockedIp::flushCache();

        return back()->with('success', 'IP address unblocked.');
    }
}
