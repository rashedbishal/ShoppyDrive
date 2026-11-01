<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PendingCheckout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MED-20 Tier 1 only. Prunes PendingCheckout rows that have already been
 * converted (order_number set) and are well past their 2-hour window — at
 * that point the row is pure leftover bookkeeping, since the real Order it
 * created already exists independently.
 *
 * Tier 2 — expired rows with order_number still NULL — is deliberately left
 * entirely alone. A ZiniPay payment that succeeded but failed to create an
 * order (PaymentController::callback()'s InsufficientStockException/
 * CouponUnavailableException handling) leaves exactly this shape on purpose,
 * so an admin can resolve it manually; nothing on the row itself tells a
 * truly-abandoned checkout apart from one that collected real money. See
 * the remediation tracker (MED-20) for why Tier 2 is deferred, not fixed.
 */
class PrunePendingCheckoutsCommand extends Command
{
    private const RETENTION_HOURS = 24;

    private const CHUNK_SIZE = 200;

    protected $signature = 'pending-checkouts:prune';

    protected $description = 'Delete converted PendingCheckout rows older than 24 hours (Tier 1 only; unconverted rows are never touched)';

    public function handle(): void
    {
        $cutoff = now()->subHours(self::RETENTION_HOURS);
        $deleted = 0;
        $orphaned = 0;

        PendingCheckout::query()
            ->whereNotNull('order_number')
            ->where('expires_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($chunk) use (&$deleted, &$orphaned): void {
                $orderNumbers = $chunk->pluck('order_number')->all();

                $existingOrderNumbers = Order::query()
                    ->whereIn('order_number', $orderNumbers)
                    ->pluck('order_number')
                    ->all();

                $eligibleIds = [];

                foreach ($chunk as $pending) {
                    if (in_array($pending->order_number, $existingOrderNumbers, true)) {
                        $eligibleIds[] = $pending->id;

                        continue;
                    }

                    // Its order_number is set but no matching Order exists —
                    // an anomaly, not the expected "converted and done" case.
                    // Preserve the row and report it rather than guess.
                    $orphaned++;

                    Log::warning('[PendingCheckouts] Preserved: order_number set but no matching Order found', [
                        'pending_checkout_id' => $pending->id,
                        'order_number' => $pending->order_number,
                    ]);
                }

                if ($eligibleIds !== []) {
                    $deleted += PendingCheckout::whereIn('id', $eligibleIds)->delete();
                }
            });

        $tier2Count = PendingCheckout::query()
            ->whereNull('order_number')
            ->where('expires_at', '<', now())
            ->count();

        $this->info("Pruned {$deleted} converted pending checkout(s) older than ".self::RETENTION_HOURS.' hours.');

        if ($orphaned > 0) {
            $this->warn("Preserved {$orphaned} row(s) with a set order_number but no matching Order — see the log.");
        }

        if ($tier2Count > 0) {
            $this->line("{$tier2Count} expired, unconverted pending checkout(s) left untouched (Tier 2, deferred — see MED-20).");
        }
    }
}
