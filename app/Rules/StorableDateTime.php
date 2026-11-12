<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A date the database can actually store. Flash sale, coupon and blog dates
 * are MySQL TIMESTAMP columns, which only hold 1970 to 19 January 2038, so a
 * year like 9999 typed into a date picker passed the plain `date` rule and
 * then failed at the database with an error page. This keeps every such date
 * inside MIN..MAX, comfortably within that range.
 *
 * Use alongside `date`: a value that isn't a date at all is `date`'s to
 * report, so it's skipped here.
 */
class StorableDateTime implements ValidationRule
{
    public const MIN = '2000-01-01 00:00:00';

    public const MAX = '2037-12-31 23:59:59';

    public const MESSAGE = 'Choose a date between 1 January 2000 and 31 December 2037.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return;
        }

        if ($date->lt(Carbon::parse(self::MIN)) || $date->gt(Carbon::parse(self::MAX))) {
            $fail(self::MESSAGE);
        }
    }
}
