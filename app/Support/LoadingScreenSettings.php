<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\Request;

/**
 * The page-loading overlay's adjustable numbers (Admin > Settings > General),
 * shown by resources/js/components/shared/LoadingLogoSpinner.tsx. Sizes are
 * the desktop sizes; the spinner scales them down on smaller screens. Each is
 * validated against these bounds on save and clamped again on read, so a bad
 * stored value can never freeze, hide or overflow the loader.
 */
class LoadingScreenSettings
{
    /**
     * Longest side a stored loading logo is resized to: sharp on 2x screens
     * at the largest logo size (560px), and a small, quick-to-decode file.
     */
    public const LOGO_STORED_MAX_SIDE = 1024;

    /**
     * Largest upload accepted, per side. Bigger images can't be resized
     * safely in the server's memory and would be stored (and decoded by
     * every browser) at full size.
     */
    public const LOGO_UPLOAD_MAX_SIDE = 4096;

    /**
     * Setting key => [default, min, max, decimals stored].
     *
     * @var array<string, array{0: int|float, 1: int|float, 2: int|float, 3: int}>
     */
    public const FIELDS = [
        // Seconds for one full turn of the ring (1.1 = the original speed).
        'loading_spin_seconds' => [1.1, 0.3, 10, 1],
        // Outer diameter of the ring, in pixels.
        'loading_ring_size' => [340, 80, 600, 0],
        // Width and height of the box the logo is fitted into, in pixels
        // (224 = 4x the original 56px loader logo).
        'loading_logo_size' => [224, 32, 560, 0],
        // Thickness of the ring's line, in pixels.
        'loading_ring_thickness' => [4, 1, 20, 0],
    ];

    /**
     * Validation for whichever of these fields a request carries: a field
     * sent must have a value; the speed takes at most one decimal place; the
     * sizes are whole pixels; and the logo may not be larger than the ring —
     * the one submitted alongside it, else the one already saved.
     *
     * @param  array<string, mixed>  $stored  current setting values, keyed by setting key
     * @return array<string, list<mixed>>
     */
    public static function rules(Request $request, array $stored): array
    {
        $rules = [];

        foreach (self::FIELDS as $key => [, $min, $max, $decimals]) {
            $rules[$key] = [
                'sometimes', 'required', 'numeric',
                $decimals === 0 ? 'integer' : 'decimal:0,'.$decimals,
                'min:'.$min, 'max:'.$max,
            ];
        }

        $submittedRing = $request->input('loading_ring_size');
        $ringSize = is_numeric($submittedRing)
            ? (float) $submittedRing
            : self::forFrontend($stored)['loading_ring_size'];

        $rules['loading_logo_size'][] = function (string $attribute, mixed $value, Closure $fail) use ($ringSize): void {
            if (is_numeric($value) && (float) $value > $ringSize) {
                $fail('The logo size cannot be larger than the ring diameter ('.(int) $ringSize.' px).');
            }
        };

        return $rules;
    }

    /**
     * Readable names for validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'loading_spin_seconds' => 'speed',
            'loading_ring_size' => 'ring diameter',
            'loading_logo_size' => 'logo size',
            'loading_ring_thickness' => 'ring thickness',
        ];
    }

    /** The value to store for a validated input: a real number, not the string a multipart request carries. */
    public static function normalize(string $key, mixed $value): int|float
    {
        [, , , $decimals] = self::FIELDS[$key];

        return $decimals === 0 ? (int) round((float) $value) : round((float) $value, $decimals);
    }

    /**
     * Every field for the frontend, defaulted and clamped.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, int|float>
     */
    public static function forFrontend(array $stored): array
    {
        $values = [];

        foreach (self::FIELDS as $key => [$default, $min, $max]) {
            $raw = $stored[$key] ?? null;
            $value = is_numeric($raw) ? self::normalize($key, $raw) : $default;
            $values[$key] = max($min, min($max, $value));
        }

        return $values;
    }
}
