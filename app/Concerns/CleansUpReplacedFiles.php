<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\UnusedUploadRemover;
use Illuminate\Database\Eloquent\Model;

/**
 * Removes an uploaded file from the public disk once the row that used it no
 * longer does: when the file column is changed (a new image uploaded, an
 * outside URL pasted, the image cleared) and when the row is permanently
 * deleted. A soft delete keeps the file, since the row can come back.
 *
 * Each model lists its file columns in PUBLIC_FILE_COLUMNS; the deleting
 * itself — after commit, upload folders only, only when nothing else still
 * points at the file — is UnusedUploadRemover's.
 *
 * Model events don't fire for query-builder `update()`/`delete()`, so bulk
 * actions on these models must go through each model.
 */
trait CleansUpReplacedFiles
{
    public static function bootCleansUpReplacedFiles(): void
    {
        static::updated(function (Model $model): void {
            foreach (static::PUBLIC_FILE_COLUMNS as $column) {
                if ($model->wasChanged($column)) {
                    static::removeFileIfUnused($model->getRawOriginal($column));
                }
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            foreach (static::PUBLIC_FILE_COLUMNS as $column) {
                static::removeFileIfUnused($model->getRawOriginal($column));
            }
        });
    }

    private static function removeFileIfUnused(mixed $value): void
    {
        app(UnusedUploadRemover::class)->removeIfUnused(is_string($value) ? $value : null);
    }
}
