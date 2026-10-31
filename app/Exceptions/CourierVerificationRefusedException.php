<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * PAY-RETURN-001: a delivery verification or readiness attestation was
 * refused. The message is safe to show the admin; `field` is the form field
 * it belongs to.
 */
class CourierVerificationRefusedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'verification')
    {
        parent::__construct($message);
    }
}
