<?php

declare(strict_types=1);

namespace App\Support\Health;

use RuntimeException;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

/**
 * DISC-37: the package's disk check runs the `df` shell command, which
 * shared hosting may block. This measures the same thing with PHP's own
 * disk_total_space()/disk_free_space() on the app's folder. On shared
 * hosting that is the server's disk, which can differ from the hosting
 * plan's own storage quota.
 */
class DiskSpaceCheck extends UsedDiskSpaceCheck
{
    protected function getDiskUsagePercentage(): int
    {
        $path = $this->filesystemName ?: base_path();
        $total = function_exists('disk_total_space') ? @disk_total_space($path) : false;
        $free = function_exists('disk_free_space') ? @disk_free_space($path) : false;

        if (! $total || $free === false) {
            throw new RuntimeException('The disk space could not be read on this server.');
        }

        return (int) round(($total - $free) / $total * 100);
    }
}
