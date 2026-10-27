<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function forProduct(string $productName, int $available): self
    {
        $message = $available > 0
            ? "Only {$available} unit(s) of \"{$productName}\" left in stock."
            : "\"{$productName}\" is out of stock.";

        return new self($message);
    }

    public static function forFlashSaleLimit(string $productName): self
    {
        return new self("The flash-sale stock limit for \"{$productName}\" has been reached.");
    }
}
