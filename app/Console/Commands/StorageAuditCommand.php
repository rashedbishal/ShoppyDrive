<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PublicFileReferences;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Finds uploaded files nothing uses any more — a replaced image, a removed
 * landing slide, an editor image taken out of a post, an upload never saved —
 * and, only when asked, moves them aside.
 *
 * A file counts as unused when it sits in one of the upload folders
 * (PublicFileReferences::UPLOAD_DIRECTORIES), no database row mentions its
 * path (so old orders' product photos, trashed rows and checkouts in progress
 * keep theirs), and it is older than that folder's grace period. Files in any
 * other folder are only reported.
 *
 * Nothing is deleted outright: --quarantine moves files to the private disk
 * under storage-quarantine/<batch>/ with a manifest, --restore puts a batch
 * back, and --purge deletes batches older than PURGE_AFTER_DAYS.
 */
class StorageAuditCommand extends Command
{
    public const QUARANTINE_DIRECTORY = 'storage-quarantine';

    public const PURGE_AFTER_DAYS = 30;

    protected $signature = 'storage:audit
        {--list : Also list every unused file}
        {--quarantine : Move unused files to the quarantine folder}
        {--restore= : Put a quarantined batch back where it was}
        {--purge : Permanently delete quarantine batches older than 30 days}';

    protected $description = 'Report uploaded files nothing uses any more, and optionally move them to a quarantine folder';

    public function handle(PublicFileReferences $references): int
    {
        if ($this->option('restore') !== null) {
            return $this->restore((string) $this->option('restore'));
        }

        if ($this->option('purge')) {
            return $this->purge();
        }

        $public = Storage::disk('public');
        $referenced = $references->allReferencedPaths();
        $summary = [];
        $unused = [];

        foreach ($public->allFiles() as $path) {
            $directory = str_contains($path, '/') ? explode('/', $path)[0] : '(top level)';

            if (str_starts_with($path, '.') || str_contains($path, '/.')) {
                continue;
            }

            $size = $public->size($path);
            $summary[$directory] ??= ['files' => 0, 'bytes' => 0, 'unused' => 0, 'unused_bytes' => 0];
            $summary[$directory]['files']++;
            $summary[$directory]['bytes'] += $size;

            if ($this->isUnused($public, $path, $directory, $referenced)) {
                $summary[$directory]['unused']++;
                $summary[$directory]['unused_bytes'] += $size;
                $unused[] = $path;
            }
        }

        ksort($summary);
        $this->report($summary);

        if ($this->option('list')) {
            foreach ($unused as $path) {
                $this->line('  '.$path);
            }
        }

        if (! $this->option('quarantine')) {
            if ($unused !== []) {
                $this->newLine();
                $this->line('Nothing was changed. Run with --quarantine to move the unused files aside (they can be restored).');
            }

            return self::SUCCESS;
        }

        return $this->quarantine($public, $unused);
    }

    /** @param  array<string, true>  $referenced */
    private function isUnused(Filesystem $public, string $path, string $directory, array $referenced): bool
    {
        $graceDays = PublicFileReferences::UPLOAD_DIRECTORIES[$directory] ?? null;

        return $graceDays !== null
            && ! isset($referenced[$path])
            && $public->lastModified($path) < now()->subDays($graceDays)->getTimestamp();
    }

    /** @param  array<string, array{files: int, bytes: int, unused: int, unused_bytes: int}>  $summary */
    private function report(array $summary): void
    {
        $rows = [];
        $total = ['files' => 0, 'bytes' => 0, 'unused' => 0, 'unused_bytes' => 0];

        foreach ($summary as $directory => $counts) {
            $managed = isset(PublicFileReferences::UPLOAD_DIRECTORIES[$directory]);
            $rows[] = [
                $directory,
                $counts['files'],
                $this->megabytes($counts['bytes']),
                $managed ? $counts['unused'] : 'not checked',
                $managed ? $this->megabytes($counts['unused_bytes']) : '',
            ];

            foreach ($total as $key => $value) {
                $total[$key] = $value + $counts[$key];
            }
        }

        $rows[] = ['TOTAL', $total['files'], $this->megabytes($total['bytes']), $total['unused'], $this->megabytes($total['unused_bytes'])];

        $this->table(['Folder', 'Files', 'Size', 'Unused', 'Unused size'], $rows);
    }

    /** @param  list<string>  $unused */
    private function quarantine(Filesystem $public, array $unused): int
    {
        if ($unused === []) {
            $this->info('No unused files to move.');

            return self::SUCCESS;
        }

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

        $this->info('Moved '.count($moved)." unused files to quarantine batch {$batch}.");
        $this->line("Put them back with: php artisan storage:audit --restore={$batch}");
        $this->line('Batches older than '.self::PURGE_AFTER_DAYS.' days are deleted with: php artisan storage:audit --purge');

        return count($moved) === count($unused) ? self::SUCCESS : self::FAILURE;
    }

    private function restore(string $batch): int
    {
        $local = Storage::disk('local');
        $manifest = self::QUARANTINE_DIRECTORY.'/'.$batch.'/manifest.json';

        if (preg_match('/^[0-9_\-]+$/', $batch) !== 1 || ! $local->exists($manifest)) {
            $this->error("No quarantine batch named \"{$batch}\".");

            return self::FAILURE;
        }

        $public = Storage::disk('public');
        $restored = 0;
        $skipped = 0;

        foreach ((array) (json_decode((string) $local->get($manifest), true)['files'] ?? []) as $path) {
            $source = self::QUARANTINE_DIRECTORY.'/'.$batch.'/'.$path;

            // A file uploaded since under the same name is never overwritten.
            if (! is_string($path) || ! $local->exists($source) || $public->exists($path)) {
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

        $this->info("Restored {$restored} files from batch {$batch}.".($skipped > 0 ? " {$skipped} skipped (already present or missing)." : ''));

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $local = Storage::disk('local');
        $cutoff = now()->subDays(self::PURGE_AFTER_DAYS)->getTimestamp();
        $purged = 0;

        foreach ($local->directories(self::QUARANTINE_DIRECTORY) as $directory) {
            $manifest = $directory.'/manifest.json';
            $movedAt = $local->exists($manifest) ? $local->lastModified($manifest) : PHP_INT_MAX;

            if ($movedAt < $cutoff) {
                $local->deleteDirectory($directory);
                $purged++;
            }
        }

        $this->info("Deleted {$purged} quarantine ".($purged === 1 ? 'batch' : 'batches').' older than '.self::PURGE_AFTER_DAYS.' days.');

        return self::SUCCESS;
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
