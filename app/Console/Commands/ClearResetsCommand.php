<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearResetsCommand extends Command
{
    protected $signature = 'auth:clear-resets';

    protected $description = 'Delete expired password reset tokens';

    public function handle(): void
    {
        Artisan::call('auth:clear-resets');
        $this->info('Expired password reset tokens deleted.');
    }
}
