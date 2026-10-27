<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CouponUnavailableException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
