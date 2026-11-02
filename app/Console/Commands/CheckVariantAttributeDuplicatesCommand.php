<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\VariantAttributeDuplicateCheck;
use Illuminate\Console\Command;
use Throwable;

class CheckVariantAttributeDuplicatesCommand extends Command
{
    protected $signature = 'catalog:check-variant-attribute-duplicates';

    protected $description = 'Read-only: list product variant SKUs or attribute values shared by more than one row (must be none before the MED-06 unique-index migration can add that index).';

    public function handle(): int
    {
        try {
            $duplicates = VariantAttributeDuplicateCheck::find();
        } catch (Throwable $e) {
            $this->error('Could not complete the duplicate check: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! VariantAttributeDuplicateCheck::hasDuplicates($duplicates)) {
            $this->info('No duplicate SKUs or attribute values found. The MED-06 unique-index migration can add both indexes.');

            return self::SUCCESS;
        }

        $this->error('Duplicate SKUs or attribute values found — the MED-06 migration will skip the affected index until these are resolved manually:');
        foreach (explode("\n", VariantAttributeDuplicateCheck::describe($duplicates)) as $line) {
            $this->line($line);
        }

        return self::FAILURE;
    }
}
