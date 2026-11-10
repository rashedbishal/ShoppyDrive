<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Admin;
use App\Models\ReceivedSms;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * PERF-006 (web push follow-up): replaces the admin layout's 15-second SMS
 * poll — pushed the instant a new SMS is stored, instead of discovered on
 * the next tick. Queued so a slow/unreachable push service never delays the
 * forwarder's own webhook response (SmsTestController::receive()).
 */
class NewSmsReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ReceivedSms $sms) {}

    /** @return array<class-string> */
    public function via(Admin $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toWebPush(Admin $notifiable): array
    {
        return [
            'title' => 'New SMS received',
            'body' => trim($this->sms->sender.' — '.$this->sms->content) ?: 'A new SMS arrived.',
            'url' => route('admin.received-sms.index'),
            'data' => [
                'type' => 'new-sms',
                'id' => $this->sms->id,
                'sender' => $this->sms->sender,
                'content' => $this->sms->content,
                'parsed_transaction_id' => $this->sms->parsed_transaction_id,
                'parsed_amount' => $this->sms->parsed_amount,
            ],
        ];
    }
}
