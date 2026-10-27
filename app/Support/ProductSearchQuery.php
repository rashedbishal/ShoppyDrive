<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The products.name/sku/short_description substring search used identically
 * by the catalog listing, search results, and autocomplete endpoints.
 *
 * On MySQL, `name`/`short_description` are matched via the FULLTEXT index
 * (boolean mode: each query word required, matched as a whole word or a
 * word-prefix) instead of a leading-wildcard LIKE, which can never use an
 * index. `sku` is deliberately left as a plain LIKE — SKU fragments are
 * code-like substrings, not words, and FULLTEXT tokenizing would silently
 * break legitimate partial-SKU lookups (e.g. pasting the middle of a SKU).
 *
 * Trade-off (intentional, documented — not a bug to "fix" later): a search
 * term shorter than 3 characters, or made up only of symbols, can't use
 * FULLTEXT (InnoDB's minimum indexed token size) and falls back to the
 * original substring LIKE query for name/short_description too, so short
 * terms behave exactly as before. The same LIKE fallback is used
 * unconditionally on any non-MySQL connection (this app's test suite runs
 * on SQLite, which has no FULLTEXT/MATCH support at all), so functional
 * behavior in tests exercises the pre-existing, unchanged query shape.
 * A pure substring match in the *middle* of a word (e.g. "hirt" matching
 * "shirt") is no longer possible once a term reaches 3+ characters on
 * MySQL — only whole-word and word-prefix matches are. Multi-word queries
 * are, if anything, more permissive than before: the old LIKE required the
 * exact phrase to appear verbatim and contiguously, whereas boolean mode
 * matches when every word is present anywhere in the field, in any order.
 *
 * Two further, narrower trade-offs, deliberately accepted rather than
 * engineered around (verified against real MySQL — see Issue #18/#18-review):
 * - A query consisting *only* of English stopwords ("the", "and", ...) will
 *   match nothing via FULLTEXT even if that literal word appears in a
 *   product name, since InnoDB's stopword list excludes them from the index
 *   entirely (`innodb_ft_enable_stopword`). Falling back to LIKE whenever
 *   FULLTEXT returns zero rows would reintroduce the exact full-scan cost
 *   this class exists to avoid, on precisely the case (no matches) that's
 *   most common and most expensive — so this isn't done. In practice a
 *   standalone stopword has no product-identifying value as a search term.
 * - Short (<3 char) syllables glued into a longer indexed word by MySQL's
 *   own tokenizer at index time — e.g. "Wi-Fi" is indexed as "Wi" (dropped,
 *   too short) + "Fi" (dropped, too short), not "wifi" — can't be found by
 *   searching that syllable, on either side of the index. This is inherent
 *   to how the stored content itself gets tokenized, not something a
 *   change to query-term handling here can work around.
 */
class ProductSearchQuery
{
    /**
     * Add the name/sku/short_description OR-condition to an existing query
     * builder (or sub-query closure), as the first condition in that group.
     */
    public static function apply(Builder $query, string $term, bool $includeShortDescription = true): Builder
    {
        $booleanQuery = self::buildBooleanQuery($term);

        if ($booleanQuery !== null) {
            $columns = $includeShortDescription ? ['name', 'short_description'] : ['name'];
            $query->whereFullText($columns, $booleanQuery, ['mode' => 'boolean']);
        } else {
            $query->where('name', 'like', "%{$term}%");

            if ($includeShortDescription) {
                $query->orWhere('short_description', 'like', "%{$term}%");
            }
        }

        return $query->orWhere('sku', 'like', "%{$term}%");
    }

    /**
     * Build a boolean-mode AGAINST() query string from free-text input, or
     * null when FULLTEXT can't be used on this connection at all.
     */
    private static function buildBooleanQuery(string $term): ?string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return null;
        }

        return self::toBooleanModeQuery($term);
    }

    /**
     * Pure string transformation — no DB dependency, so it's unit-testable
     * on any connection — turning free-text input into a boolean-mode
     * AGAINST() query string ("+word1* +word2*"), or null when no word
     * reaches the minimum indexed token length (3 characters) after
     * stripping boolean-mode operator characters.
     *
     * Boolean-mode operator characters are replaced with a space — not
     * deleted — so they act as word separators. Deleting them instead would
     * glue adjacent words together (e.g. "T-Shirt" -> "TShirt", a token
     * nothing is ever indexed as, since MySQL's own word parser splits
     * "T-Shirt" into "T" and "Shirt" when indexing); confirmed against real
     * MySQL that this previously made searching for "T-Shirt" fail to find
     * a product literally named "Classic T-Shirt".
     */
    public static function toBooleanModeQuery(string $term): ?string
    {
        $sanitized = preg_replace('/[+\-><()~*"@]/', ' ', $term);

        $tokens = collect(preg_split('/\s+/', trim($sanitized), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word): bool => mb_strlen($word) >= 3)
            ->map(fn (string $word): string => '+'.$word.'*');

        return $tokens->isEmpty() ? null : $tokens->implode(' ');
    }
}
