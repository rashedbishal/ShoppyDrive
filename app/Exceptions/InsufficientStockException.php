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

    /**
     * FSA-01: an order line whose variant belongs to a different product.
     * Reuses this exception so every order path's existing handler rolls
     * the order back and shows the customer an error.
     */
    public static function forMismatchedVariant(): self
    {
        return new self('One of the selected product options is not available.');
    }

    /**
     * BIZ-011: too many units of one product in this phone number's COD
     * orders that no courier has picked up yet (see PendingCodLimit).
     */
    public static function forPendingCodLimit(string $productName, int $limit, int $alreadyHeld): self
    {
        $message = "Cash on delivery allows at most {$limit} of \"{$productName}\" per phone number until earlier orders are dispatched.";

        if ($alreadyHeld > 0) {
            $message .= " You already have {$alreadyHeld} in orders waiting to be dispatched.";
        }

        return new self($message.' Please reduce the quantity, pay by bKash/Nagad, or contact us.');
    }

    public static function forFlashSaleLimit(string $productName): self
    {
        return new self("The flash-sale stock limit for \"{$productName}\" has been reached.");
    }
}
