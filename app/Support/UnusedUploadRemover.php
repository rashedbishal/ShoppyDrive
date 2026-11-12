<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes uploaded files a change has just stopped using.
 *
 * Each value is a stored "/storage/..." address. A file is deleted only when
 * it sits in one of the upload folders, still exists, and nothing else in
 * the database points at it (PublicFileReferences — e.g. an old order's
 * product photo stays). The check runs after the surrounding transaction
 * commits; if it rolls back, Laravel drops the callback and nothing is
 * deleted. A failure is logged, never thrown: the save already succeeded.
 */
class UnusedUploadRemover
{
    public function __construct(private readonly PublicFileReferences $references) {}

    public function removeIfUnused(?string ...$values): void
    {
        $paths = array_values(array_unique(array_filter(array_map(PublicFileReferences::uploadPath(...), $values))));

        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths): void {
            foreach ($paths as $path) {
                try {
                    if (Storage::disk('public')->exists($path) && ! $this->references->isReferenced($path)) {
                        Storage::disk('public')->delete($path);
                    }
                } catch (Throwable $exception) {
                    Log::warning('[Uploads] Could not remove an unused file', ['path' => $path, 'message' => $exception->getMessage()]);
                }
            }
        });
    }
}
