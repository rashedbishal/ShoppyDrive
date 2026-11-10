<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Admin;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * PERF-006 (web push follow-up): delivers a notification to every browser
 * an Admin has opted into push on. Best-effort — a failed or unreachable
 * push must never break whatever triggered it (the SMS-forwarder webhook,
 * the silence-check command), so every failure is caught and logged, never
 * thrown. An expired/unsubscribed-at-the-browser subscription (410 Gone, or
 * the library's own isSubscriptionExpired() check) is deleted here, so dead
 * rows don't accumulate and don't keep getting (uselessly) retried.
 */
class WebPushChannel
{
    public function send(Admin $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable));
        $webPush = $this->client();

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), $payload);
            } catch (Throwable $e) {
                Log::warning('[WebPush] Could not queue a notification', ['subscription_id' => $subscription->id, 'message' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $notifiable->pushSubscriptions()->where('endpoint', $report->getEndpoint())->delete();

                continue;
            }

            Log::warning('[WebPush] Delivery failed', ['endpoint' => $report->getEndpoint(), 'reason' => $report->getReason()]);
        }
    }

    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ],
        ]);
    }
}
