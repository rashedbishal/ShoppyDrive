<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ConsignmentDuplicateCheck;
use Illuminate\Console\Command;

class CheckConsignmentDuplicatesCommand extends Command
{
    protected $signature = 'courier:check-consignment-duplicates';

    protected $description = 'Read-only: list courier consignment IDs shared by more than one order (must be none before the unique-index migration runs).';

    public function handle(): int
    {
        $duplicates = ConsignmentDuplicateCheck::find();

        if ($duplicates === []) {
            $this->info('No duplicate courier consignment IDs found. The unique-index migration can run.');

            return self::SUCCESS;
        }

        $this->error('Duplicate courier consignment IDs found — the unique-index migration will refuse to run until they are resolved manually:');
        foreach (explode("\n", ConsignmentDuplicateCheck::describe($duplicates)) as $line) {
            $this->line($line);
        }

        return self::FAILURE;
    }
}
