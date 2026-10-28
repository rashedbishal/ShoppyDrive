<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearResetsCommand extends Command
{
    /**
     * Deliberately distinct from Laravel's own built-in `auth:clear-resets`
     * command, which this wraps — reusing that exact name would make
     * Symfony Console register this class in place of the original (the
     * last-registered command with a given name wins), so the
     * `Artisan::call('auth:clear-resets')` below would then resolve back to
     * this same class and recurse indefinitely.
     */
    protected $signature = 'app:clear-expired-resets';

    protected $description = 'Delete expired password reset tokens';

    public function handle(): void
    {
        Artisan::call('auth:clear-resets');
        $this->info('Expired password reset tokens deleted.');
    }
}
