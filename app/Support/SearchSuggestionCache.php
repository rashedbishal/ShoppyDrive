<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches the search bar's live suggestions per exact search text.
 *
 * - Cleared on any change suggestions depend on (see the
 *   FlushesSearchSuggestions trait, plus explicit flush() calls after bulk
 *   writes that fire no model events). Clearing bumps a version stamp
 *   baked into every key, because the file store can't delete by pattern.
 * - Expires after a few minutes regardless, as a backstop for changes no
 *   event announces: a product whose scheduled `published_at` time arrives.
 * - Keyed on the exact text (no lowercasing or space-squeezing) so a cached
 *   answer is always the one the database would have given for that text.
 * - Empty results and very long texts are never cached: on shared hosting
 *   each entry is a file, and junk searches mostly match nothing, so bots
 *   can't fill the disk with one-off entries.
 * - Stored as the JSON-decoded response: exactly what the client receives,
 *   and guaranteed object-free (cache.serializable_classes is false, so a
 *   nested Collection would come back from the file store as
 *   __PHP_Incomplete_Class).
 */
final class SearchSuggestionCache
{
    public const TTL_SECONDS = 300;

    public const MAX_CACHED_QUERY_LENGTH = 64;

    private const VERSION_KEY = 'search_suggestions.version';

    /**
     * @param  Closure(): array<string, array<int, mixed>>  $build
     * @return array<string, array<int, mixed>>
     */
    public static function remember(string $query, Closure $build): array
    {
        if (mb_strlen($query) > self::MAX_CACHED_QUERY_LENGTH) {
            return json_decode(json_encode($build(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        }

        $key = 'search_suggestions.'.self::version().'.'.sha1($query);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = json_decode(json_encode($build(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        if (collect($result)->contains(fn (array $group): bool => $group !== [])) {
            Cache::put($key, $result, self::TTL_SECONDS);
        }

        return $result;
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    private static function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn (): string => Str::random(12));
    }
}
