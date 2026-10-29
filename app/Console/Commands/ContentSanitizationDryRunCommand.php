<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

/**
 * Read-only preview of what the Stage 1 sanitizer would change in
 * already-stored content. Never calls save()/update()/delete()/upsert() —
 * every record is loaded, run through Purifier::clean() in memory, and
 * compared; nothing is written back. The whole scan also runs inside a
 * transaction that is always rolled back, as a second, redundant guarantee
 * against an accidental write slipping in regardless of the code above it.
 *
 * Deliberately refuses to run outside a local/testing environment — this
 * reads live content, and an operator pointing it at the wrong database by
 * mistake should get an immediate, clear refusal rather than a silent scan.
 */
class ContentSanitizationDryRunCommand extends Command
{
    protected $signature = 'content:sanitization-dry-run';

    protected $description = 'Preview (read-only) what Stage 1 HTML sanitization would change in existing Blog/Product/LandingPage content';

    private const SNIPPET_LENGTH = 300;

    public function handle(): int
    {
        if (! App::environment(['local', 'testing'])) {
            $this->error('Refusing to run: this command reads live content and is restricted to local/testing environments. Current environment: '.App::environment());

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            $this->scanBlogContent();
            $this->scanProductDescriptions();
            $this->scanLandingPageCustomContent();

            // Read-only by construction above; this is a second, independent
            // guarantee that nothing persists even if that ever changes.
            DB::rollBack();
        });

        return self::SUCCESS;
    }

    private function scanBlogContent(): void
    {
        $this->scanColumn(
            label: 'Blog.content',
            query: Blog::query()->select(['id', 'slug', 'content']),
            identify: fn (Blog $blog): string => $blog->slug,
            extractOriginal: fn (Blog $blog): ?string => $blog->getRawOriginal('content'),
        );
    }

    private function scanProductDescriptions(): void
    {
        $this->scanColumn(
            label: 'Product.description',
            query: Product::query()->select(['id', 'slug', 'description']),
            identify: fn (Product $product): string => $product->slug,
            extractOriginal: fn (Product $product): ?string => $product->getRawOriginal('description'),
        );
    }

    private function scanLandingPageCustomContent(): void
    {
        $this->scanColumn(
            label: 'LandingPage.sections.description.custom_content',
            query: LandingPage::query()->select(['id', 'slug', 'sections']),
            identify: fn (LandingPage $page): string => $page->slug,
            extractOriginal: fn (LandingPage $page): ?string => $page->sections['description']['custom_content'] ?? null,
        );
    }

    private function scanColumn(string $label, Builder $query, \Closure $identify, \Closure $extractOriginal): void
    {
        $total = 0;
        $unchanged = 0;
        $changed = [];

        foreach ($query->cursor() as $record) {
            $total++;

            $original = $extractOriginal($record);

            if (! is_string($original) || $original === '') {
                $unchanged++;

                continue;
            }

            $sanitized = Purifier::clean($original);

            if ($sanitized === $original) {
                $unchanged++;

                continue;
            }

            $changed[] = [
                'id' => $record->getKey(),
                'identifier' => $identify($record),
                'original_snippet' => $this->snippet($original),
                'sanitized_snippet' => $this->snippet($sanitized),
                'classification' => $this->classify($original, $sanitized),
            ];
        }

        $this->newLine();
        $this->components->twoColumnDetail("<fg=cyan>{$label}</>", "{$total} total");
        $this->components->twoColumnDetail('Unchanged', (string) $unchanged);
        $this->components->twoColumnDetail('Would change', (string) count($changed));

        foreach ($changed as $row) {
            $this->newLine();
            $this->line("  [{$row['id']}] {$row['identifier']}");
            $this->line('    classification: '.$row['classification']);
            $this->line('    before: '.$row['original_snippet']);
            $this->line('    after:  '.$row['sanitized_snippet']);
        }
    }

    private function snippet(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return mb_strlen($value) > self::SNIPPET_LENGTH
            ? mb_substr($value, 0, self::SNIPPET_LENGTH).'…'
            : $value;
    }

    /**
     * Best-effort classification for a human reviewer, not a security
     * guarantee either way — "unsafe HTML removed" is reported whenever a
     * known-dangerous construct present in the original is absent from the
     * sanitized output; "formatting potentially lost" when the only
     * difference looks like styling (inline `style=`/`<font`) disappearing;
     * anything else is left as a neutral "other change" rather than forcing
     * a guess.
     */
    private function classify(string $original, string $sanitized): string
    {
        $dangerousPatterns = [
            '/<script/i', '/<iframe/i', '/<object/i', '/<embed/i', '/<svg/i',
            '/\bon\w+\s*=/i', '/javascript:/i', '/data:/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $original) === 1 && preg_match($pattern, $sanitized) !== 1) {
                return 'unsafe HTML removed';
            }
        }

        if ((stripos($original, 'style=') !== false || stripos($original, '<font') !== false)
            && stripos($sanitized, 'style=') === false && stripos($sanitized, '<font') === false) {
            return 'formatting potentially lost (inline style/color)';
        }

        return 'other change';
    }
}
