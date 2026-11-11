<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\ReceivedSms;
use App\Notifications\NewSmsReceived;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PERF-006 (web push follow-up): the single place a new SMS arrival is
 * pushed to admins — only to those who can actually view the section the
 * notification links to, same gate as the `received_sms.view` route
 * middleware. Admin accounts are never numerous, so filtering in PHP via
 * the existing per-instance Admin::canAccess() is simpler and no slower
 * than a bespoke permissions query.
 */
class SmsAdminNotifier
{
    /**
     * Sent right after the current response has been delivered (terminating
     * callback), not through the queue: instant, yet the SMS forwarder phone
     * never waits for the push services. Never throws.
     */
    public static function notifyNewSms(ReceivedSms $sms): void
    {
        $sent = false;

        app()->terminating(function () use ($sms, &$sent): void {
            // Terminating callbacks stay registered on a long-lived app
            // instance (tests, workers); this one must run exactly once.
            if ($sent) {
                return;
            }

            $sent = true;

            try {
                Admin::all()
                    ->filter(fn (Admin $admin): bool => $admin->canAccess('received_sms'))
                    ->each(fn (Admin $admin) => $admin->notifyNow(new NewSmsReceived($sms)));
            } catch (Throwable $e) {
                Log::warning('[WebPush] Could not notify admins of a new SMS', ['message' => $e->getMessage()]);
            }
        });
    }
}
