<?php

declare(strict_types=1);

namespace App\Support;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a file on the public disk is still used anywhere, by searching
 * every text column of every table for its path. Shared by the clean-up that
 * runs when an image is replaced or deleted (CleansUpReplacedFiles) and by
 * `storage:audit`, so both follow one rule: a file anything still points at
 * stays — a product photo an old order's snapshot shows, a trashed row, an
 * anonymized customer's restore snapshot, a checkout in progress.
 *
 * Laravel's own bookkeeping tables are not "uses", and neither is the audit
 * trail, which records old paths as history.
 */
class PublicFileReferences
{
    /**
     * Folders uploads are saved in, with how many days a file there must go
     * unreferenced before `storage:audit` treats it as unused: the default
     * covers an editor or landing-slide upload the admin hasn't saved yet;
     * a newsletter email keeps loading its image for months after sending.
     * Files anywhere else on the public disk are only ever reported.
     *
     * @var array<string, int>
     */
    public const UPLOAD_DIRECTORIES = [
        'avatars' => 7,
        'blogs' => 7,
        'brands' => 7,
        'categories' => 7,
        'content-images' => 7,
        'hero-slides' => 7,
        'landing-slides' => 7,
        'logos' => 7,
        'newsletter-images' => 90,
        'product-bundles' => 7,
        'products' => 7,
        'promotional-banners' => 7,
        'reviews' => 7,
        'sub-categories' => 7,
    ];

    /** @var list<string> */
    private const IGNORED_TABLES = [
        'activity_log', 'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
        'migrations', 'password_reset_tokens', 'sessions',
    ];

    /** @var list<string> */
    private const TEXT_TYPES = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json', 'jsonb', 'string'];

    /** @var array<string, list<string>>|null table => its text columns */
    private ?array $textColumns = null;

    /**
     * The public-disk path ("categories/abc.webp") a stored value points at
     * — "/storage/categories/abc.webp" — when it's a file inside one of the
     * upload folders; null for anything else (an outside URL, a placeholder).
     */
    public static function uploadPath(?string $value): ?string
    {
        if ($value === null || ! str_starts_with($value, '/storage/') || str_contains($value, '..')) {
            return null;
        }

        $path = substr($value, strlen('/storage/'));
        $directory = explode('/', $path)[0];

        return isset(self::UPLOAD_DIRECTORIES[$directory]) && str_contains($path, '/') ? $path : null;
    }

    /** Whether any row still mentions this public-disk path. */
    public function isReferenced(string $path): bool
    {
        // Text columns holding JSON may store the slashes escaped ("\/").
        $needles = array_unique([$path, str_replace('/', '\\/', $path)]);

        $grammar = DB::connection()->getQueryGrammar();

        foreach ($this->textColumns() as $table => $columns) {
            $used = DB::table($table)->where(function ($query) use ($columns, $needles, $grammar): void {
                foreach ($columns as $column) {
                    foreach ($needles as $needle) {
                        // An explicit escape character: MySQL and SQLite
                        // disagree on whether a backslash escapes by default.
                        $query->orWhereRaw(
                            $grammar->wrap($column)." like ? escape '!'",
                            ['%'.addcslashes($needle, '%_!').'%'],
                        );
                    }
                }
            })->exists();

            if ($used) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every public-disk path any row mentions, for checking many files at
     * once. Values are streamed, so a large table is never held in memory.
     *
     * @return array<string, true>
     */
    public function allReferencedPaths(): array
    {
        $paths = [];

        foreach ($this->valuesMentioningStorage() as $value) {
            $value = str_replace('\\/', '/', $value);

            if (preg_match_all('#storage/([A-Za-z0-9._~\-]+(?:/[A-Za-z0-9._~\-]+)+)#', $value, $matches) > 0) {
                foreach ($matches[1] as $path) {
                    $paths[$path] = true;
                }
            }
        }

        return $paths;
    }

    /** @return Generator<int, string> */
    private function valuesMentioningStorage(): Generator
    {
        foreach ($this->textColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $rows = DB::table($table)->select($column)->where($column, 'like', '%storage%')->cursor();

                foreach ($rows as $row) {
                    yield (string) $row->{$column};
                }
            }
        }
    }

    /** @return array<string, list<string>> */
    private function textColumns(): array
    {
        if ($this->textColumns !== null) {
            return $this->textColumns;
        }

        $this->textColumns = [];

        // This database only: on MySQL an unqualified listing also includes
        // every other database the user can see (e.g. old test databases).
        foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false) as $table) {
            if (in_array($table, self::IGNORED_TABLES, true)) {
                continue;
            }

            $columns = array_values(array_map(
                fn (array $column): string => $column['name'],
                array_filter(
                    Schema::getColumns($table),
                    fn (array $column): bool => in_array(strtolower((string) $column['type_name']), self::TEXT_TYPES, true),
                ),
            ));

            if ($columns !== []) {
                $this->textColumns[$table] = $columns;
            }
        }

        return $this->textColumns;
    }
}
