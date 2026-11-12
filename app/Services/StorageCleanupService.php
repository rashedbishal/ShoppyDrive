<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PublicFileReferences;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Finds uploaded files nothing uses any more — a replaced image, a removed
 * landing slide, an editor image taken out of a post, an upload never saved —
 * and moves them aside, restores them, or deletes them for good. Shared by
 * the `storage:audit` command and Admin › Storage Clean-up.
 *
 * A file counts as unused when it sits in one of the upload folders
 * (PublicFileReferences::UPLOAD_DIRECTORIES), no database row mentions its
 * path (so old orders' product photos, trashed rows and checkouts in progress
 * keep theirs), and it is older than that folder's grace period. Files in any
 * other folder are only reported.
 *
 * Nothing is deleted straight away: quarantine() moves files to the private
 * disk under storage-quarantine/<batch>/ with a manifest, restore() puts a
 * batch back, and a batch can only be deleted once it is PURGE_AFTER_DAYS old.
 */
class StorageCleanupService
{
    public const QUARANTINE_DIRECTORY = 'storage-quarantine';

    public const PURGE_AFTER_DAYS = 30;

    public function __construct(private readonly PublicFileReferences $references) {}

    /**
     * @return array{
     *     folders: list<array{folder: string, checked: bool, files: int, bytes: int, unused: int, unused_bytes: int}>,
     *     unused: list<array{path: string, bytes: int}>,
     *     totals: array{files: int, bytes: int, unused: int, unused_bytes: int}
     * }
     */
    public function report(): array
    {
        $public = Storage::disk('public');
        $referenced = $this->references->allReferencedPaths();
        $folders = [];
        $unused = [];

        foreach ($public->allFiles() as $path) {
            if (str_starts_with($path, '.') || str_contains($path, '/.')) {
                continue;
            }

            $folder = str_contains($path, '/') ? explode('/', $path)[0] : '(top level)';
            $size = $public->size($path);
            $folders[$folder] ??= [
                'folder' => $folder,
                'checked' => isset(PublicFileReferences::UPLOAD_DIRECTORIES[$folder]),
                'files' => 0, 'bytes' => 0, 'unused' => 0, 'unused_bytes' => 0,
            ];
            $folders[$folder]['files']++;
            $folders[$folder]['bytes'] += $size;

            if ($this->isUnused($public, $path, $folder, $referenced)) {
                $folders[$folder]['unused']++;
                $folders[$folder]['unused_bytes'] += $size;
                $unused[] = ['path' => $path, 'bytes' => $size];
            }
        }

        ksort($folders);
        $totals = ['files' => 0, 'bytes' => 0, 'unused' => 0, 'unused_bytes' => 0];

        foreach ($folders as $counts) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $counts[$key];
            }
        }

        return ['folders' => array_values($folders), 'unused' => $unused, 'totals' => $totals];
    }

    /**
     * Moves every currently unused file into a new quarantine batch.
     *
     * @return array{batch: string|null, moved: int, failed: int}
     */
    public function quarantine(): array
    {
        $unused = array_column($this->report()['unused'], 'path');

        if ($unused === []) {
            return ['batch' => null, 'moved' => 0, 'failed' => 0];
        }

        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $batch = now()->format('Y-m-d_His');
        $moved = [];

        foreach ($unused as $path) {
            $target = self::QUARANTINE_DIRECTORY.'/'.$batch.'/'.$path;

            if ($local->writeStream($target, $public->readStream($path)) && $public->delete($path)) {
                $moved[] = $path;
            }
        }

        $local->put(self::QUARANTINE_DIRECTORY.'/'.$batch.'/manifest.json', (string) json_encode([
            'moved_at' => now()->toIso8601String(),
            'files' => $moved,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['batch' => $batch, 'moved' => count($moved), 'failed' => count($unused) - count($moved)];
    }

    /**
     * Every quarantine batch, newest first.
     *
     * @return list<array{batch: string, moved_at: int, files: int, bytes: int, purgeable_at: int, purgeable: bool}>
     */
    public function batches(): array
    {
        $local = Storage::disk('local');
        $batches = [];

        foreach ($local->directories(self::QUARANTINE_DIRECTORY) as $directory) {
            $manifest = $directory.'/manifest.json';

            if (! $local->exists($manifest)) {
                continue;
            }

            $movedAt = $local->lastModified($manifest);
            $files = array_values(array_filter($local->allFiles($directory), fn (string $file): bool => $file !== $manifest));
            $purgeableAt = $movedAt + self::PURGE_AFTER_DAYS * 86400;

            $batches[] = [
                'batch' => basename($directory),
                'moved_at' => $movedAt,
                'files' => count($files),
                'bytes' => array_sum(array_map($local->size(...), $files)),
                'purgeable_at' => $purgeableAt,
                'purgeable' => $purgeableAt <= now()->getTimestamp(),
            ];
        }

        usort($batches, fn (array $a, array $b): int => $b['moved_at'] <=> $a['moved_at']);

        return $batches;
    }

    /**
     * Puts a batch's files back where they were; a file uploaded since under
     * the same name is never overwritten. Null when there's no such batch.
     *
     * @return array{restored: int, skipped: int}|null
     */
    public function restore(string $batch): ?array
    {
        $local = Storage::disk('local');
        $manifest = $this->manifestPath($batch);

        if ($manifest === null) {
            return null;
        }

        $public = Storage::disk('public');
        $restored = 0;
        $skipped = 0;

        foreach ((array) (json_decode((string) $local->get($manifest), true)['files'] ?? []) as $path) {
            $source = self::QUARANTINE_DIRECTORY.'/'.$batch.'/'.$path;

            if (! is_string($path) || str_contains($path, '..') || ! $local->exists($source) || $public->exists($path)) {
                $skipped++;

                continue;
            }

            $public->writeStream($path, $local->readStream($source));
            $local->delete($source);
            $restored++;
        }

        if ($skipped === 0) {
            $local->deleteDirectory(self::QUARANTINE_DIRECTORY.'/'.$batch);
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }

    /**
     * Permanently deletes one batch, only once it is old enough.
     *
     * @return bool|null null: no such batch; false: not old enough yet
     */
    public function deleteBatch(string $batch): ?bool
    {
        if ($this->manifestPath($batch) === null) {
            return null;
        }

        foreach ($this->batches() as $candidate) {
            if ($candidate['batch'] === $batch) {
                return $candidate['purgeable'] && Storage::disk('local')->deleteDirectory(self::QUARANTINE_DIRECTORY.'/'.$batch);
            }
        }

        return null;
    }

    /** Permanently deletes every batch old enough; returns how many. */
    public function purgeOldBatches(): int
    {
        $purged = 0;

        foreach ($this->batches() as $batch) {
            if ($batch['purgeable'] && Storage::disk('local')->deleteDirectory(self::QUARANTINE_DIRECTORY.'/'.$batch['batch'])) {
                $purged++;
            }
        }

        return $purged;
    }

    /** @param  array<string, true>  $referenced */
    private function isUnused(Filesystem $public, string $path, string $folder, array $referenced): bool
    {
        $graceDays = PublicFileReferences::UPLOAD_DIRECTORIES[$folder] ?? null;

        return $graceDays !== null
            && ! isset($referenced[$path])
            && $public->lastModified($path) < now()->subDays($graceDays)->getTimestamp();
    }

    /** The batch's manifest path, or null for an unknown or malformed batch name. */
    private function manifestPath(string $batch): ?string
    {
        $manifest = self::QUARANTINE_DIRECTORY.'/'.$batch.'/manifest.json';

        return preg_match('/^[0-9_\-]+$/', $batch) === 1 && Storage::disk('local')->exists($manifest) ? $manifest : null;
    }
}
