<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Meta's Conversions API did not accept an event. `retryable` separates a
 * temporary failure (timeout, 429, 5xx, or Meta flagging the error as
 * transient) from a permanent rejection (invalid data) that would fail the
 * same way every time. The message is already sanitized — never the access
 * token or customer data.
 */
class MetaConversionsApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly ?int $responseStatus = null,
    ) {
        parent::__construct($message);
    }
}
