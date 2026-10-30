<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of one attempt to book an order with a courier — see
 * App\Services\CourierSubmissionService. SUBMITTING and UNKNOWN are
 * "unresolved": while one exists the order can't be sent again (to either
 * courier), cancelled or marked refunded. SENT and FAILED are final.
 */
enum CourierSubmissionState: string
{
    case SUBMITTING = 'submitting';
    case SENT = 'sent';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';

    public function isUnresolved(): bool
    {
        return $this === self::SUBMITTING || $this === self::UNKNOWN;
    }
}
