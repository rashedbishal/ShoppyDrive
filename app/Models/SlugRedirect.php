<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsSlugRedirects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * DISC-91: an old slug of a renamed product, category or sub-category.
 * Written only by RecordsSlugRedirects; read only when no live record has
 * the requested slug, so a current page always wins over a redirect.
 */
class SlugRedirect extends Model
{
    /** Every model whose old slugs are kept; used to prune rows whose record is gone. */
    public const REDIRECTABLE_MODELS = [Product::class, Category::class, SubCategory::class];

    protected $fillable = ['namespace', 'old_slug', 'redirectable_type', 'redirectable_id'];

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The 301 for an old slug, or a 404 when there's nowhere safe to send
     * it: no row, the record is gone or soft-deleted, or it isn't publicly
     * visible. The target is always built by the record from its own route,
     * so it can't point anywhere else; the query string is kept.
     */
    public static function redirectOrNotFound(string $namespace, string $slug, Request $request): RedirectResponse
    {
        $record = self::query()
            ->where('namespace', $namespace)
            ->where('old_slug', $slug)
            ->first()
            ?->redirectable;

        /** @var (Model&RecordsSlugRedirects)|null $record */
        $url = $record?->currentPublicUrl();

        // A destination equal to the requested URL would loop; it can't
        // happen while live pages are resolved first, but is refused anyway.
        abort_if($url === null || rtrim($url, '/') === rtrim($request->url(), '/'), 404);

        $query = $request->getQueryString();

        return redirect()->to($query ? $url.'?'.$query : $url, 301);
    }

    /**
     * Rows whose record no longer exists, even soft-deleted. A category's
     * permanent delete removes its sub-categories and products in the
     * database without model events, so this runs after any permanent
     * delete of a redirectable model.
     */
    public static function pruneOrphans(): void
    {
        foreach (self::REDIRECTABLE_MODELS as $class) {
            self::query()
                ->where('redirectable_type', (new $class)->getMorphClass())
                ->whereNotIn('redirectable_id', $class::withTrashed()->select('id'))
                ->delete();
        }
    }
}
