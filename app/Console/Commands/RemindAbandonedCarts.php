<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AbandonedCartReminderJob;
use App\Models\IncompleteOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cart:remind-abandoned')]
#[Description('Dispatch an abandoned-cart reminder email for logged-in customers who left a checkout attempt at least an hour ago and haven\'t been reminded yet. Guest attempts (phone only, no email on file) are skipped — see AbandonedCartReminderJob.')]
class RemindAbandonedCarts extends Command
{
    public function handle(): int
    {
        $eligible = IncompleteOrder::query()
            ->whereNotNull('user_id')
            ->whereNull('reminder_sent_at')
            ->where('created_at', '<=', now()->subHour())
            ->get(['id']);

        foreach ($eligible as $incompleteOrder) {
            AbandonedCartReminderJob::dispatch($incompleteOrder->id);
        }

        $this->info("Queued {$eligible->count()} abandoned-cart reminder(s).");

        return self::SUCCESS;
    }
}
