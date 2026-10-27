<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ReturnQuantityExceededException extends RuntimeException
{
    public static function forOrderItem(string $productName, int $remaining): self
    {
        $message = $remaining > 0
            ? "Only {$remaining} unit(s) of \"{$productName}\" can still be returned from this order."
            : "\"{$productName}\" has already been fully returned (or is part of another pending/approved return) on this order.";

        return new self($message);
    }
}
