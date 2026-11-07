<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\AbandonedCartReminderJob;
use App\Jobs\SendNewsletterChunk;
use App\Mail\ContactFormMail;
use App\Mail\LowStockAlertMail;
use App\Mail\NewOrderAlertMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Mail\ReviewApprovedMail;
use App\Mail\ShippedWithTrackingMail;
use App\Mail\WelcomeMail;

/**
 * What staff may see of a `failed_jobs` row. The payload holds the serialized
 * job, including recipients, and is only ever JSON-decoded for its metadata,
 * never unserialized or passed on. The exception text can quote addresses or
 * query values, so only a redacted first line of it leaves this class.
 */
final class FailedJobSummary
{
    /**
     * Safe to run again because each only sends a message, with no order,
     * payment, refund or stock effect, or re-checks its own state first. A
     * retry could repeat an email the mail server accepted before failing.
     * Anything not listed can't be retried from the admin panel.
     *
     * AbandonedCartReminderMail is left out: the job that checks a reminder is
     * still wanted has already passed, so a late retry could chase a customer
     * who has since ordered.
     *
     * @var list<class-string>
     */
    private const RETRYABLE_JOBS = [
        OrderPlacedMail::class,
        NewOrderAlertMail::class,
        ShippedWithTrackingMail::class,
        OrderDeliveredMail::class,
        OrderCancelledMail::class,
        OrderStatusUpdatedMail::class,
        ReturnApprovedMail::class,
        ReturnRejectedMail::class,
        ReviewApprovedMail::class,
        WelcomeMail::class,
        LowStockAlertMail::class,
        AbandonedCartReminderJob::class,
        ContactFormMail::class,
        SendNewsletterChunk::class,
    ];

    /** BE-012: one failed group's mail may have partly gone out. */
    private const NEWSLETTER_RETRY_WARNING = 'This group of up to 50 subscribers will be sent again. Some of them may already have it and will get it twice. Anyone who has unsubscribed is skipped.';

    private const MAX_ERROR_LENGTH = 240;

    /**
     * @return array{uuid: string, queue: string, connection: string, job: string, max_tries: int|null, failed_at: string, error_type: string, error: string, can_retry: bool}
     */
    public static function from(object $failedJob): array
    {
        $payload = json_decode((string) $failedJob->payload, true);
        $jobClass = is_array($payload) ? (string) ($payload['displayName'] ?? '') : '';
        [$errorType, $error] = self::summarizeException((string) $failedJob->exception);

        return [
            'uuid' => (string) $failedJob->uuid,
            'queue' => (string) $failedJob->queue,
            'connection' => (string) $failedJob->connection,
            'job' => $jobClass !== '' ? class_basename($jobClass) : 'Unknown job',
            'max_tries' => isset($payload['maxTries']) ? (int) $payload['maxTries'] : null,
            'failed_at' => (string) $failedJob->failed_at,
            'error_type' => $errorType,
            'error' => $error,
            'can_retry' => self::canRetry($failedJob),
            'retry_warning' => $jobClass === SendNewsletterChunk::class ? self::NEWSLETTER_RETRY_WARNING : null,
        ];
    }

    public static function canRetry(object $failedJob): bool
    {
        $payload = json_decode((string) $failedJob->payload, true);

        return is_array($payload) && in_array($payload['displayName'] ?? null, self::RETRYABLE_JOBS, true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function summarizeException(string $exception): array
    {
        $firstLine = strtok($exception, "\n") ?: '';

        if (preg_match('/^([\w\\\\]+)(?:: |\s*$)(.*)$/', $firstLine, $matches) !== 1) {
            return ['Error', self::redact($firstLine)];
        }

        return [class_basename($matches[1]), self::redact($matches[2])];
    }

    private static function redact(string $message): string
    {
        // Everything after these markers is SQL, stack detail or a file path.
        $message = (string) preg_replace('/\s*(\(Connection:|\(SQL:|SQL:| in \/).*$/s', '', $message);

        $patterns = [
            '/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/' => '[email]',
            '#\b[a-z][a-z0-9+.\-]*://\S+#i' => '[url]',
            '/\b(?:\+?88)?01[3-9]\d{8}\b/' => '[phone]',
            '/\b\d{1,3}(?:\.\d{1,3}){3}\b/' => '[ip]',
            '/\b(password|passwd|pwd|secret|token|api[_-]?key|authorization|bearer)\b\s*[:=]?\s*\S+/i' => '$1 [hidden]',
            '/\b[A-Za-z0-9_\-+\/=]{24,}\b/' => '[hidden]',
            '/\b\d{6,}\b/' => '[number]',
        ];

        return mb_strimwidth(trim((string) preg_replace(array_keys($patterns), array_values($patterns), $message)), 0, self::MAX_ERROR_LENGTH, '…');
    }
}
