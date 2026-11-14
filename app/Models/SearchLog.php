<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Daily search-term counts for the admin dashboard. No visitor data is kept.
 */
class SearchLog extends Model
{
    public const MAX_TERM_LENGTH = 100;

    protected $fillable = [
        'date',
        'term',
        'searches',
        'results_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'searches' => 'integer',
            'results_count' => 'integer',
        ];
    }

    /**
     * Counts one search for today (Dhaka), keeping the latest result count.
     * Never throws: a logging problem must not break the search page.
     */
    public static function record(string $term, int $resultsCount): void
    {
        $term = mb_strtolower(trim($term));

        if ($term === '') {
            return;
        }

        $term = mb_substr($term, 0, self::MAX_TERM_LENGTH);
        $today = now('Asia/Dhaka')->toDateString();

        try {
            $updated = self::query()
                ->whereDate('date', $today)
                ->where('term', $term)
                ->update([
                    'searches' => DB::raw('searches + 1'),
                    'results_count' => $resultsCount,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                try {
                    self::create(['date' => $today, 'term' => $term, 'searches' => 1, 'results_count' => $resultsCount]);
                } catch (QueryException) {
                    // Another request created today's row first.
                    self::query()->whereDate('date', $today)->where('term', $term)->increment('searches');
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
