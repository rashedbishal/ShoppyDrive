<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one place an admin-authored SMS regex runs. A pattern gates automatic
 * real-money payment acceptance, so every failure is reported rather than
 * suppressed, and the caller treats it as "nothing parsed", which the payment
 * checks then refuse.
 */
class SmsRegex
{
    /** Far longer than any real payment SMS, even a concatenated one. */
    public const MAX_CONTENT_LENGTH = 2000;

    /**
     * Stops catastrophic backtracking within milliseconds, while a real
     * pattern against a real SMS needs a tiny fraction of it.
     */
    private const BACKTRACK_LIMIT = 100000;

    /**
     * @param  string|null  $failure  Set to why the regex could not run. Never contains the SMS.
     */
    public static function firstCapture(string $regex, string $content, ?string &$failure = null): ?string
    {
        $failure = null;
        $result = self::run($regex, $content, $matches);

        if ($result === false) {
            $failure = preg_last_error_msg();

            return null;
        }

        return $result === 1 ? ($matches[1] ?? null) : null;
    }

    /**
     * Why this regex must not be saved, or null if it may. The probes can't
     * prove a pattern safe, so the runtime limit stays the real protection.
     * Each one ends with the regex's own text, because PCRE skips matching
     * when a required literal is absent, which would hide a pattern like
     * `(\d+)+x` until a real SMS contained that literal.
     */
    public static function problem(string $regex): ?string
    {
        if (self::run($regex, '', $matches) === false) {
            return 'This is not a valid regular expression. Write it without surrounding slashes, and escape any / as \/.';
        }

        if (self::run("(?:{$regex})|", '', $matches, PREG_UNMATCHED_AS_NULL) === false || count($matches) < 2) {
            return 'This must contain a capture group, in brackets, around the value to read.';
        }

        foreach (['a', 'A', '1', ' ', '.', "\n"] as $character) {
            $probe = str_repeat($character, self::MAX_CONTENT_LENGTH).'!'.$regex;

            if (self::run($regex, $probe, $matches) === false) {
                return 'This pattern is too slow on long text. Avoid nested repeats such as (a+)+.';
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>|null  $matches
     */
    private static function run(string $regex, string $content, ?array &$matches, int $flags = 0): int|false
    {
        $previousLimit = ini_set('pcre.backtrack_limit', (string) self::BACKTRACK_LIMIT);

        try {
            return @preg_match('/'.$regex.'/i', $content, $matches, $flags);
        } finally {
            if ($previousLimit !== false) {
                ini_set('pcre.backtrack_limit', $previousLimit);
            }
        }
    }
}
