<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StorageCleanupService;
use Illuminate\Console\Command;

/**
 * The command-line face of StorageCleanupService (the same as Admin ›
 * Storage Clean-up): report uploaded files nothing uses, move them to a
 * restorable quarantine, put a batch back, or delete old batches for good.
 */
class StorageAuditCommand extends Command
{
    /** Kept for existing references; the service owns the value. */
    public const QUARANTINE_DIRECTORY = StorageCleanupService::QUARANTINE_DIRECTORY;

    protected $signature = 'storage:audit
        {--list : Also list every unused file}
        {--quarantine : Move unused files to the quarantine folder}
        {--restore= : Put a quarantined batch back where it was}
        {--purge : Permanently delete quarantine batches older than 30 days}';

    protected $description = 'Report uploaded files nothing uses any more, and optionally move them to a quarantine folder';

    public function handle(StorageCleanupService $cleanup): int
    {
        if ($this->option('restore') !== null) {
            $batch = (string) $this->option('restore');
            $result = $cleanup->restore($batch);

            if ($result === null) {
                $this->error("No quarantine batch named \"{$batch}\".");

                return self::FAILURE;
            }

            $this->info("Restored {$result['restored']} files from batch {$batch}.".($result['skipped'] > 0 ? " {$result['skipped']} skipped (already present or missing)." : ''));

            return self::SUCCESS;
        }

        if ($this->option('purge')) {
            $purged = $cleanup->purgeOldBatches();
            $this->info("Deleted {$purged} quarantine ".($purged === 1 ? 'batch' : 'batches').' older than '.StorageCleanupService::PURGE_AFTER_DAYS.' days.');

            return self::SUCCESS;
        }

        $report = $cleanup->report();
        $this->printReport($report);

        if ($this->option('list')) {
            foreach ($report['unused'] as $file) {
                $this->line('  '.$file['path']);
            }
        }

        if (! $this->option('quarantine')) {
            if ($report['unused'] !== []) {
                $this->newLine();
                $this->line('Nothing was changed. Run with --quarantine to move the unused files aside (they can be restored).');
            }

            return self::SUCCESS;
        }

        $result = $cleanup->quarantine();

        if ($result['batch'] === null) {
            $this->info('No unused files to move.');

            return self::SUCCESS;
        }

        $this->info("Moved {$result['moved']} unused files to quarantine batch {$result['batch']}.");
        $this->line("Put them back with: php artisan storage:audit --restore={$result['batch']}");
        $this->line('Batches older than '.StorageCleanupService::PURGE_AFTER_DAYS.' days are deleted with: php artisan storage:audit --purge');

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array{folders: list<array<string, mixed>>, totals: array<string, int>}  $report */
    private function printReport(array $report): void
    {
        $rows = array_map(fn (array $folder): array => [
            $folder['folder'],
            $folder['files'],
            $this->megabytes($folder['bytes']),
            $folder['checked'] ? $folder['unused'] : 'not checked',
            $folder['checked'] ? $this->megabytes($folder['unused_bytes']) : '',
        ], $report['folders']);

        $totals = $report['totals'];
        $rows[] = ['TOTAL', $totals['files'], $this->megabytes($totals['bytes']), $totals['unused'], $this->megabytes($totals['unused_bytes'])];

        $this->table(['Folder', 'Files', 'Size', 'Unused', 'Unused size'], $rows);
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
