<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Admin\NewsletterController;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FSA-13 backstop for newsletter uploads. A send's attachments normally
 * disappear with its batch's `then` callback once every chunk is delivered.
 * This removes what that can't:
 *
 * - a send whose batch is finished but whose directory survived (the
 *   callback failed);
 * - a directory with no batch at all after a day (the request died between
 *   storing the files and dispatching);
 * - a send left with permanently failed chunks for ABANDONED_AFTER_DAYS.
 *   A later retry of such a chunk then fails instead of sending without
 *   attachments (SendNewsletterChunk checks they exist);
 * - legacy `tmp/` uploads from the old synchronous sender, which nothing
 *   reads after its request ended.
 *
 * A directory whose batch is still running is never touched.
 */
class PruneNewsletterAttachmentsCommand extends Command
{
    private const ORPHAN_AFTER_HOURS = 24;

    private const ABANDONED_AFTER_DAYS = 30;

    protected $signature = 'newsletter:prune-attachments';

    protected $description = 'Delete newsletter attachment uploads whose send has finished, was never queued, or was abandoned';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $root = NewsletterController::ATTACHMENT_ROOT;
        $deleted = 0;

        foreach ($disk->directories($root) as $directory) {
            if (basename($directory) === 'tmp') {
                $deleted += $this->pruneLegacyTmp($disk, $directory);

                continue;
            }

            if ($this->isPrunable($disk, $directory)) {
                $disk->deleteDirectory($directory);
                $deleted++;
            }
        }

        $this->info("Removed {$deleted} newsletter attachment upload(s).");

        return self::SUCCESS;
    }

    private function isPrunable(Filesystem $disk, string $directory): bool
    {
        $batch = DB::table('job_batches')
            ->where('name', NewsletterController::BATCH_NAME_PREFIX.basename($directory))
            ->first(['finished_at']);

        $ageInSeconds = now()->getTimestamp() - $this->newestFileTime($disk, $directory);

        return match (true) {
            $batch !== null && $batch->finished_at !== null => true,
            $batch === null => $ageInSeconds > self::ORPHAN_AFTER_HOURS * 3600,
            default => $ageInSeconds > self::ABANDONED_AFTER_DAYS * 86400,
        };
    }

    private function pruneLegacyTmp(Filesystem $disk, string $directory): int
    {
        $deleted = 0;

        foreach ($disk->files($directory) as $file) {
            if (now()->getTimestamp() - $disk->lastModified($file) > self::ORPHAN_AFTER_HOURS * 3600) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * An empty directory counts as infinitely old.
     */
    private function newestFileTime(Filesystem $disk, string $directory): int
    {
        $times = array_map(fn (string $file): int => $disk->lastModified($file), $disk->allFiles($directory));

        return $times === [] ? 0 : max($times);
    }
}
