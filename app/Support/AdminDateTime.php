<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Converts between an admin form's `datetime-local` value, which has no zone
 * and means Bangladesh time, and the UTC the application stores and compares.
 */
class AdminDateTime
{
    private const TIMEZONE = 'Asia/Dhaka';

    /**
     * A value that states its own offset keeps it; otherwise it's Bangladesh time.
     */
    public static function fromInput(string $value): Carbon
    {
        return Carbon::parse($value, self::TIMEZONE)->setTimezone(config('app.timezone'));
    }

    /**
     * Converts the named fields that hold a value, leaving empty ones as they are.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function fieldsFromInput(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (! empty($data[$field])) {
                $data[$field] = self::fromInput($data[$field]);
            }
        }

        return $data;
    }

    /**
     * An empty date stays an empty input.
     */
    public static function toInput(?CarbonInterface $moment): string
    {
        return $moment?->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i') ?? '';
    }
}
