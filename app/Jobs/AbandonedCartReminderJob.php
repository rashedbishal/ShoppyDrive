<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AbandonedCartReminderMail;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Reminds a logged-in customer about items left at checkout. Only
 * dispatched by `cart:remind-abandoned` for rows that have a `user_id`
 * (and therefore a real account email) — a guest abandoned checkout only
 * ever captures a phone number (see IncompleteOrder/IncompleteOrderController),
 * so there is no address to email a guest reminder to; that gap is a data-
 * capture limitation, not something this job can work around.
 */
class AbandonedCartReminderJob implements ShouldQueue
{
    use Queueable;

    /** OPS-006 (owner decision 2026-11-11): at most one reminder per customer per 7 days. */
    public const COOLDOWN_DAYS = 7;

    public function __construct(
        public readonly int $incompleteOrderId,
    ) {}

    public function handle(): void
    {
        // Claim this reminder atomically — lock the row, verify
        // eligibility, and mark it reminded, all inside one transaction
        // that commits *before* any mail is sent (see below). Two workers
        // racing on the same row can never both pass this check: the
        // second blocks on the lock, then re-reads the now-committed
        // reminder_sent_at once the first finishes and correctly skips.
        //
        // This guarantees at-most-once *sending* under contention, not
        // exactly-once *delivery*. If the Mail::send() call below throws
        // after the claim has already committed, the queue may retry this
        // job depending on its tries/backoff configuration — but a retry
        // will see reminder_sent_at already set and skip resending. That's
        // an intentional trade-off for a reminder email: avoiding a
        // duplicate send matters more here than guaranteeing delivery of
        // any single attempt, so a transient failure after a successful
        // claim means the customer may simply never get this reminder,
        // rather than risking two.
        $claimed = DB::transaction(function (): ?IncompleteOrder {
            // OPS-006: the customer's row is locked first, then this cart's —
            // the same order checkout uses (it locks the user, then deletes
            // their IncompleteOrder rows), so the two can't deadlock. Locking
            // the customer also serializes this customer's other reminder
            // jobs, so the per-customer cooldown below can't be raced.
            $userId = IncompleteOrder::whereKey($this->incompleteOrderId)->value('user_id');
            $user = $userId === null ? null : User::lockForUpdate()->find($userId);

            $incompleteOrder = IncompleteOrder::lockForUpdate()->find($this->incompleteOrderId);

            if ($incompleteOrder === null || $incompleteOrder->reminder_sent_at !== null) {
                return null;
            }

            if ($user === null || (int) $incompleteOrder->user_id !== $user->id || ! $user->email) {
                return null;
            }

            $incompleteOrder->setRelation('user', $user);

            // OPS-006: User::anonymize() deliberately leaves the email
            // column untouched (so refund/audit history stays legible),
            // so this account still looks mailable without this check — a
            // customer who deleted their account must not still be mailed.
            if ($incompleteOrder->user->anonymized_at !== null) {
                $incompleteOrder->update(['reminder_sent_at' => now()]);

                return null;
            }

            // OPS-006: opt-out, checked here (not in the command's query)
            // since it's a per-row claim decision like the others below.
            if (! $incompleteOrder->user->abandoned_cart_reminders_enabled) {
                $incompleteOrder->update(['reminder_sent_at' => now()]);

                return null;
            }

            // OPS-006: never mail an address the customer hasn't proven is
            // theirs. Left unclaimed, so a customer who verifies before the
            // 72-hour ceiling can still be reminded on a later run.
            if ($user->email_verified_at === null) {
                return null;
            }

            // Defense in depth: the order-creation paths that clean up a
            // matching IncompleteOrder row (by phone or user_id) might
            // have missed this one for some other reason — e.g. a third
            // phone number, or a future order-creation path that forgets
            // to clean up. If this customer has placed *any* order since
            // this cart was abandoned, they've clearly already been
            // through checkout, so don't remind them about it. Claimed
            // (marked as handled) the same as a real send, so this row
            // isn't re-evaluated every hour forever.
            $hasOrderedSince = Order::where('user_id', $incompleteOrder->user_id)
                ->where('created_at', '>=', $incompleteOrder->created_at)
                ->exists();

            // OPS-006: at most one reminder per customer per cooldown, however
            // many checkout attempts they abandoned — `reminder_sent_at` only
            // dedupes per attempt. An attempt inside the cooldown is claimed
            // without sending; with a cooldown longer than the command's
            // 72-hour ceiling, it would have expired before the next chance.
            $inCooldown = $user->last_abandoned_cart_reminder_at !== null
                && $user->last_abandoned_cart_reminder_at->gt(now()->subDays(self::COOLDOWN_DAYS));

            $incompleteOrder->update(['reminder_sent_at' => now()]);

            if ($hasOrderedSince || $inCooldown) {
                return null;
            }

            $user->forceFill(['last_abandoned_cart_reminder_at' => now()])->save();

            return $incompleteOrder;
        });

        if ($claimed === null) {
            return;
        }

        Mail::to($claimed->user->email)->send(new AbandonedCartReminderMail($claimed));
    }
}
