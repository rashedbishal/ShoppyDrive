<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Model;

/**
 * DISC-91: keeps a record's previous slugs so its old URL 301s to the
 * current one (see SlugRedirect). The using model declares
 * `SLUG_REDIRECT_NAMESPACE` (the URL its slug lives under) and implements
 * currentPublicUrl().
 *
 * - A slug change stores the old slug, pointing at this record (one row per
 *   namespace + old slug; a later owner of the same old slug takes it over).
 * - Every save removes any row for the record's current slug in its
 *   namespace, so a slug that is live again (a → b → a, or reused by another
 *   record) is never redirected away: no loops, and live pages win.
 * - Rows point at the record, not at the new slug, so a → b → c sends both a
 *   and b straight to c.
 * - A permanent delete removes the record's rows (and any left by cascaded
 *   deletes). Soft deletes keep them: the redirect 404s while the record is
 *   trashed and works again if it's restored.
 */
trait RecordsSlugRedirects
{
    /** The record's current public URL, or null when it may not be shown (then the old URL 404s). */
    abstract public function currentPublicUrl(): ?string;

    public static function bootRecordsSlugRedirects(): void
    {
        static::updated(function (Model $model): void {
            $oldSlug = $model->getOriginal('slug');

            if ($model->wasChanged('slug') && is_string($oldSlug) && $oldSlug !== '') {
                SlugRedirect::updateOrCreate(
                    ['namespace' => static::SLUG_REDIRECT_NAMESPACE, 'old_slug' => $oldSlug],
                    ['redirectable_type' => $model->getMorphClass(), 'redirectable_id' => $model->getKey()],
                );
            }
        });

        static::saved(function (Model $model): void {
            SlugRedirect::query()
                ->where('namespace', static::SLUG_REDIRECT_NAMESPACE)
                ->where('old_slug', $model->getAttribute('slug'))
                ->delete();
        });

        static::forceDeleted(function (Model $model): void {
            SlugRedirect::query()
                ->where('redirectable_type', $model->getMorphClass())
                ->where('redirectable_id', $model->getKey())
                ->delete();

            SlugRedirect::pruneOrphans();
        });
    }
}
