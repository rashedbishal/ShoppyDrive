<?php

declare(strict_types=1);

namespace App\Support;

/**
 * FE-012: the one place a customer-entered Bangladeshi mobile number is
 * cleaned. Mirrored in resources/js/lib/phone.ts for live input.
 *
 * Bangla digits (০–৯) become 0–9; spaces, dashes, dots and brackets are
 * removed; a +880 / 00880 / 880 country prefix becomes the local leading 0.
 * Anything else is returned unchanged so validation can reject it.
 */
final class BangladeshiPhone
{
    /** 11 digits, operator prefix 013–019. */
    public const PATTERN = '/^01[3-9][0-9]{8}$/';

    public const INVALID_MESSAGE = 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).';

    private const BANGLA_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = str_replace(self::BANGLA_DIGITS, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], trim($raw));
        $value = str_replace([' ', '-', '.', '(', ')'], '', $value);

        if (str_starts_with($value, '+880')) {
            return '0'.substr($value, 4);
        }

        if (str_starts_with($value, '00880')) {
            return '0'.substr($value, 5);
        }

        if (str_starts_with($value, '880') && strlen($value) === 13) {
            return '0'.substr($value, 3);
        }

        return $value;
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && preg_match(self::PATTERN, $value) === 1;
    }
}
