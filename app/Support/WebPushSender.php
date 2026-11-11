<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Throwable;

/**
 * Delivers one push payload to every browser an admin has opted in, and
 * reports what actually happened. Shared by the SMS notification channel and
 * the "Send test notification" button, so the outcome staff see on
 * Admin > SMS Forwarder is the real one (a rejected key, an unreachable push
 * service, an expired browser sign-up) instead of silence. Never throws.
 */
class WebPushSender
{
    private const MAX_REASON_LENGTH = 160;

    /**
     * @param  array<string, mixed>  $payload
     * @param  'sms'|'test'  $kind
     * @param  ClientInterface|null  $client  PSR-18 HTTP client; only tests pass one, to avoid real network.
     * @return array{sent: int, expired: int, failed: list<string>}
     */
    public static function send(Admin $admin, array $payload, string $kind = 'sms', ?ClientInterface $client = null): array
    {
        $result = ['sent' => 0, 'expired' => 0, 'failed' => []];
        $subscriptions = $admin->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        if (! WebPushConfig::isConfigured()) {
            Log::warning('[WebPush] Skipped: no VAPID keys are set up (Admin > SMS Forwarder).');
            $result['failed'][] = 'Notification keys are not set up.';
            WebPushConfig::recordResult($kind, $result);

            return $result;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => WebPushConfig::subject(),
                    'publicKey' => (string) WebPushConfig::publicKey(),
                    'privateKey' => (string) WebPushConfig::privateKey(),
                ],
            ], [], $client, logger: new WebPushLogger);
            $encodedPayload = (string) json_encode($payload);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), $encodedPayload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $result['sent']++;

                    continue;
                }

                // The browser or push service says this sign-up is gone for good.
                if ($report->isSubscriptionExpired()) {
                    $result['expired']++;
                    $admin->pushSubscriptions()->where('endpoint', $report->getEndpoint())->delete();

                    continue;
                }

                $status = $report->getResponse()?->getStatusCode();
                $result['failed'][] = trim(($status !== null ? "HTTP {$status}: " : '').mb_substr((string) $report->getReason(), 0, self::MAX_REASON_LENGTH));
                Log::warning('[WebPush] Delivery failed', ['endpoint' => $report->getEndpoint(), 'status' => $status, 'reason' => $report->getReason()]);
            }
        } catch (Throwable $e) {
            $result['failed'][] = class_basename($e).': '.mb_substr($e->getMessage(), 0, self::MAX_REASON_LENGTH);
            Log::warning('[WebPush] Sending failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }

        WebPushConfig::recordResult($kind, $result);

        return $result;
    }
}
