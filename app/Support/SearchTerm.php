<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cleans what a visitor searched for before it costs a database query. Every
 * storefront search goes through this, since a bot calls the server directly
 * and never meets the checks in SearchBar.tsx (lib/search-term.ts mirrors them).
 */
final class SearchTerm
{
    public const MAX_LENGTH = SearchSuggestionCache::MAX_CACHED_QUERY_LENGTH;

    private const MIN_MEANINGFUL_CHARACTERS = 2;

    /**
     * Collapses whitespace and caps the length. Anything that isn't valid text,
     * such as `q[]=x` or malformed UTF-8, counts as no search at all.
     */
    public static function normalize(mixed $value): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        $collapsed = preg_replace('/[\s\p{Z}\p{Cc}]+/u', ' ', $value) ?? '';

        return trim(mb_substr(trim($collapsed), 0, self::MAX_LENGTH));
    }

    /**
     * Whether a normalized term has enough letters or numbers to look up.
     * Combining marks count, because Bangla vowel signs are marks: "কি" is one
     * letter and one mark.
     */
    public static function isSearchable(string $term): bool
    {
        return preg_match_all('/[\p{L}\p{N}\p{M}]/u', $term) >= self::MIN_MEANINGFUL_CHARACTERS;
    }
}
