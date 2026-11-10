<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PERF-006 (web push follow-up): one row per browser/device an admin has
 * opted SMS-arrival push notifications on. The subscription object comes
 * straight from the browser's PushManager.subscribe() — see
 * resources/js/hooks/useWebPushSms.ts.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user('admin')->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ],
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        $request->user('admin')->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
