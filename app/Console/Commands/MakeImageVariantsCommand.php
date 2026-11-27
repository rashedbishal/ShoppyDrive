<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImageOptimizer;
use App\Support\CatalogFilterCache;
use App\Support\ImageVariants;
use App\Support\LayoutCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * IMG-F5: makes the smaller WebP copies (ImageVariants) for storefront images
 * uploaded before copies existed. Originals are never touched: only new
 * "-160w/-400w/-800w/-1200w.webp" files are written beside them, so there is
 * nothing to back up and undoing it is deleting those files (--prune with
 * the originals gone, or by name pattern).
 *
 * Reports only, unless given --apply. Safe to re-run and to stop at any
 * time: an image whose copies exist is skipped. --limit keeps one run short
 * on shared hosting; run it again to continue.
 */
#[Signature('images:variants {--apply : Write the copies; without this it only reports} {--limit=0 : Stop after this many images (0 = all)} {--prune : Also delete copies whose original no longer exists}')]
#[Description('Make smaller WebP copies of existing storefront images (originals are not changed)')]
class MakeImageVariantsCommand extends Command
{
    public function handle(ImageOptimizer $optimizer): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $disk = Storage::disk('public');
        $pending = 0;
        $done = 0;
        $undecodable = 0;
        $written = 0;
        $bytes = 0;

        foreach (ImageVariants::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (! ImageVariants::supports($path) || ! $this->needsCopies($path)) {
                    continue;
                }

                $pending++;

                if (! $apply || ($limit > 0 && $done >= $limit)) {
                    continue;
                }

                $made = $optimizer->storeVariants($path);
                $written += $made;
                $done++;

                // Readable size but GD can't decode it (truncated, animated
                // WebP, too big for memory): it stays at one size, as before.
                if ($made === 0 && $this->needsCopies($path)) {
                    $undecodable++;
                }
            }
        }

        foreach (ImageVariants::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (ImageVariants::isVariant($path)) {
                    $bytes += (int) $disk->size($path);
                }
            }
        }

        $pruned = $this->option('prune') ? $this->prune($apply) : 0;

        if ($apply) {
            // Cached page data carries each image's srcset.
            LayoutCache::forget(LayoutCache::NAV_CATEGORIES, LayoutCache::HERO_SLIDES, LayoutCache::PROMOTIONAL_BANNERS);
            CatalogFilterCache::flush();
            $this->info(sprintf('Made %d cop(ies) for %d of %d image(s) needing them.', $written, $done, $pending));

            if ($undecodable > 0) {
                $this->line(sprintf('%d image(s) could not be copied (GD cannot decode them); they keep working at one size.', $undecodable));
            }

            if ($done < $pending) {
                $this->line(sprintf('%d image(s) left: run it again to continue.', $pending - $done));
            }
        } else {
            $this->info(sprintf('%d image(s) need smaller copies.', $pending));
            $this->line('Nothing was changed. Run with --apply to write the copies.');
        }

        if ($this->option('prune')) {
            $this->line(sprintf('%s %d cop(ies) whose original is gone.', $apply ? 'Deleted' : 'Would delete', $pruned));
        }

        $this->line(sprintf('Copies on disk now: %.1f MB.', $bytes / 1048576));

        return self::SUCCESS;
    }

    /** Some copy narrower than the original is missing. */
    private function needsCopies(string $path): bool
    {
        $disk = Storage::disk('public');
        $width = (int) (@getimagesize($disk->path($path))[0] ?? 0);

        foreach (ImageVariants::WIDTHS as $copyWidth) {
            if ($copyWidth < $width && ! $disk->exists(ImageVariants::variantPath($path, $copyWidth))) {
                return true;
            }
        }

        return false;
    }

    private function prune(bool $apply): int
    {
        $disk = Storage::disk('public');
        $orphans = 0;

        foreach (ImageVariants::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (! ImageVariants::isVariant($path)) {
                    continue;
                }

                $base = (string) preg_replace('/-\d+w\.webp$/', '', $path);
                $hasOriginal = collect(['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'])
                    ->contains(fn (string $extension): bool => $disk->exists($base.'.'.$extension));

                if (! $hasOriginal) {
                    $orphans++;

                    if ($apply) {
                        $disk->delete($path);
                    }
                }
            }
        }

        return $orphans;
    }
}
