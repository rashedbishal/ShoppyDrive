<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CourierVerificationReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * PAY-RETURN-001: the release fingerprint used by the courier verification
 * readiness attestation. Without --sha: computed from the files on disk
 * (what the readiness page shows on the server). With --sha (run locally,
 * in a git checkout): computed from exactly the files tracked at that
 * commit, with the same exclusions — the two must match byte for byte.
 */
#[Signature('courier:release-fingerprint {--sha= : Compute the fingerprint of a git commit instead of the files on disk}')]
#[Description('Print the release fingerprint used by the courier verification readiness check.')]
class CourierReleaseFingerprintCommand extends Command
{
    public function handle(CourierVerificationReadinessService $readiness): int
    {
        $sha = $this->option('sha');

        if ($sha === null || $sha === '') {
            $files = $readiness->fingerprintFiles();
            $this->line(CourierVerificationReadinessService::combine($files));
            $this->comment(count($files).' files (disk).');

            return self::SUCCESS;
        }

        if (preg_match('/^[0-9a-f]{7,40}$/i', (string) $sha) !== 1) {
            $this->error('--sha must be a commit SHA.');

            return self::FAILURE;
        }

        $tree = Process::path(base_path())->run(['git', 'ls-tree', '-r', '-z', (string) $sha]);

        if (! $tree->successful()) {
            $this->error('git ls-tree failed: '.trim($tree->errorOutput()));

            return self::FAILURE;
        }

        $blobs = [];

        foreach (array_filter(explode("\0", $tree->output())) as $entry) {
            [$meta, $path] = explode("\t", $entry, 2);
            [$mode, $type, $object] = explode(' ', $meta);

            // Regular files only (symlinks 120000 and submodules are not fingerprinted on disk either).
            if ($type === 'blob' && in_array($mode, ['100644', '100755'], true) && ! CourierVerificationReadinessService::isExcluded($path)) {
                $blobs[$path] = $object;
            }
        }

        $hashes = [];

        foreach (array_chunk($blobs, 500, true) as $chunk) {
            $batch = Process::path(base_path())->input(implode("\n", $chunk)."\n")->run(['git', 'cat-file', '--batch']);

            if (! $batch->successful()) {
                $this->error('git cat-file failed: '.trim($batch->errorOutput()));

                return self::FAILURE;
            }

            $output = $batch->output();
            $offset = 0;

            foreach (array_keys($chunk) as $path) {
                $headerEnd = strpos($output, "\n", $offset);
                $size = (int) explode(' ', substr($output, $offset, $headerEnd - $offset))[2];
                $hashes[$path] = hash('sha256', substr($output, $headerEnd + 1, $size));
                $offset = $headerEnd + 1 + $size + 1;
            }
        }

        $this->line(CourierVerificationReadinessService::combine($hashes));
        $this->comment(count($hashes).' files (commit '.$sha.').');

        return self::SUCCESS;
    }
}
