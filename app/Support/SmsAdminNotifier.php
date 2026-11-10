<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\ReceivedSms;
use App\Notifications\NewSmsReceived;

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
    public static function notifyNewSms(ReceivedSms $sms): void
    {
        Admin::all()
            ->filter(fn (Admin $admin): bool => $admin->canAccess('received_sms'))
            ->each(fn (Admin $admin) => $admin->notify(new NewSmsReceived($sms)));
    }
}
