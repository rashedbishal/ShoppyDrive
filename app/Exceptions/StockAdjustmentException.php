<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A manual stock adjustment that can't be applied (stock would go below
 * zero, the item no longer exists, …). The message is shown to the admin.
 */
class StockAdjustmentException extends RuntimeException
{
    public static function belowZero(int $current): self
    {
        return new self("You can't remove more than the current stock ({$current}).");
    }

    public static function aboveMaximum(int $maximum): self
    {
        return new self('Stock can be at most '.number_format($maximum).'.');
    }

    public static function variantRequired(): self
    {
        return new self('This product has variants, so stock is managed per variant.');
    }

    public static function passwordRequired(): self
    {
        return new self('Enter your password to change stock.');
    }
}
