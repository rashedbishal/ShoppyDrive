<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Saves and removes the photos customers attach to reviews. Photos are stored
 * through ImageOptimizer (longest side at most 1600 px, re-encoded, which also
 * drops camera metadata such as GPS) under public/reviews, and a review keeps
 * their public addresses ("/storage/reviews/...") in its `images` column.
 */
class ReviewImageStorage
{
    public const DIRECTORY = 'reviews';

    private const URL_PREFIX = '/storage/'.self::DIRECTORY.'/';

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string> public addresses, in upload order
     *
     * @throws Throwable after removing whatever had already been stored
     */
    public function store(array $files): array
    {
        $urls = [];

        try {
            foreach ($files as $file) {
                $urls[] = '/storage/'.$this->optimizer->store($file, self::DIRECTORY);
            }
        } catch (Throwable $exception) {
            $this->delete($urls);

            throw $exception;
        }

        return $urls;
    }

    /**
     * Removes stored photos from disk. Only addresses inside public/reviews are
     * ever touched, whatever a row holds, and a missing file is not an error.
     *
     * @param  list<string>|null  $urls
     */
    public function delete(?array $urls): void
    {
        foreach ($urls ?? [] as $url) {
            if (! is_string($url) || ! str_starts_with($url, self::URL_PREFIX) || str_contains($url, '..')) {
                continue;
            }

            try {
                Storage::disk('public')->delete(substr($url, strlen('/storage/')));
            } catch (Throwable $exception) {
                Log::warning('[ReviewImages] Could not delete a review photo', ['path' => $url, 'message' => $exception->getMessage()]);
            }
        }
    }
}
