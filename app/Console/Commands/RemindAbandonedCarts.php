<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AbandonedCartReminderJob;
use App\Models\IncompleteOrder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cart:remind-abandoned')]
#[Description('Dispatch an abandoned-cart reminder email for logged-in customers who left a checkout attempt between 1 and 72 hours ago and haven\'t been reminded yet. Guest attempts (phone only, no email on file) are skipped — see AbandonedCartReminderJob.')]
class RemindAbandonedCarts extends Command
{
    /**
     * OPS-006: if cron/the queue is down for a while and then recovers,
     * every backlogged row (days or weeks old) would otherwise be mailed
     * at once — a reminder about a cart that old isn't useful and looks
     * like spam. Rows older than this are simply never reminded.
     */
    private const MAX_AGE_HOURS = 72;

    public function handle(): int
    {
        $eligible = IncompleteOrder::query()
            ->whereNotNull('user_id')
            ->whereNull('reminder_sent_at')
            ->where('created_at', '<=', now()->subHour())
            ->where('created_at', '>', now()->subHours(self::MAX_AGE_HOURS))
            ->get(['id']);

        foreach ($eligible as $incompleteOrder) {
            AbandonedCartReminderJob::dispatch($incompleteOrder->id);
        }

        $this->info("Queued {$eligible->count()} abandoned-cart reminder(s).");

        return self::SUCCESS;
    }
}
