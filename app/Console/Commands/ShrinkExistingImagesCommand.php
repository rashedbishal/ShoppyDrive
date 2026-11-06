<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImageOptimizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PERF-004: one-time shrink of images uploaded before ImageOptimizer existed.
 *
 * Every file keeps its name and format, because those addresses are stored on
 * products, in page content and on other sites. It only reports unless given
 * --apply, each original is copied to the private disk first, and re-running
 * is safe: a file is only replaced when that saves real space.
 */
#[Signature('images:shrink {--apply : Replace the files; without this it only reports}')]
#[Description('Shrink images already on the public disk, keeping their addresses')]
class ShrinkExistingImagesCommand extends Command
{
    public const string BACKUP_DIRECTORY = 'image-originals';

    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** A correctly sized file under this isn't worth re-encoding. */
    private const int WORTHWHILE_BYTES = 300 * 1024;

    /** Re-encoding for less than a 10% saving only loses quality. */
    private const float MINIMUM_SAVING = 0.9;

    public function handle(ImageOptimizer $optimizer): int
    {
        $apply = (bool) $this->option('apply');
        $disk = Storage::disk('public');
        $shrunk = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        foreach ($disk->allFiles() as $path) {
            if (! in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                continue;
            }

            $absolutePath = $disk->path($path);
            $originalSize = (int) filesize($absolutePath);
            $maxSide = in_array(Str::before($path, '/'), ImageOptimizer::WIDE_DIRECTORIES, true)
                ? ImageOptimizer::WIDE_MAX_SIDE
                : ImageOptimizer::MAX_SIDE;

            if (! $this->isWorthShrinking($absolutePath, $originalSize, $maxSide)) {
                continue;
            }

            $shrunkBytes = $optimizer->reencode($absolutePath, $maxSide);

            if ($shrunkBytes === null || strlen($shrunkBytes) > $originalSize * self::MINIMUM_SAVING) {
                continue;
            }

            if ($apply) {
                $this->backUp($path, $absolutePath);
                $this->replace($absolutePath, $shrunkBytes);
            }

            $shrunk++;
            $bytesBefore += $originalSize;
            $bytesAfter += strlen($shrunkBytes);
        }

        $this->info(sprintf(
            '%s %d image(s): %.1f MB to %.1f MB.',
            $apply ? 'Shrunk' : 'Would shrink',
            $shrunk,
            $bytesBefore / 1048576,
            $bytesAfter / 1048576,
        ));

        if (! $apply) {
            $this->line('Nothing was changed. Run with --apply to replace the files.');
        }

        return self::SUCCESS;
    }

    private function isWorthShrinking(string $absolutePath, int $size, int $maxSide): bool
    {
        $dimensions = @getimagesize($absolutePath);

        if ($dimensions === false) {
            return false;
        }

        return $size > self::WORTHWHILE_BYTES || max($dimensions[0], $dimensions[1]) > $maxSide;
    }

    /**
     * Never overwrites: on a re-run the existing copy is the true original.
     */
    private function backUp(string $path, string $absolutePath): void
    {
        $backupPath = self::BACKUP_DIRECTORY.'/'.$path;

        if (! Storage::disk('local')->exists($backupPath)) {
            Storage::disk('local')->put($backupPath, (string) file_get_contents($absolutePath));
        }
    }

    /**
     * Written beside the file then renamed, so a visitor never gets a half
     * written image, and with the original's permissions so the web server
     * can still read it.
     */
    private function replace(string $absolutePath, string $bytes): void
    {
        $temporaryPath = $absolutePath.'.shrinking';

        file_put_contents($temporaryPath, $bytes);
        chmod($temporaryPath, fileperms($absolutePath) & 0777);
        rename($temporaryPath, $absolutePath);
    }
}
