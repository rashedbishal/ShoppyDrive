<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Admin;
use App\Support\WebPushSender;
use Illuminate\Notifications\Notification;

/**
 * PERF-006 (web push follow-up): delivers a notification to every browser
 * an Admin has opted into push on. Best-effort — a failed or unreachable
 * push must never break whatever triggered it (the SMS-forwarder webhook),
 * and WebPushSender never throws; it records what happened for the admin
 * page instead. An expired browser sign-up (410 Gone) is deleted there.
 */
class WebPushChannel
{
    public function send(Admin $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        WebPushSender::send($notifiable, $notification->toWebPush($notifiable));
    }
}
