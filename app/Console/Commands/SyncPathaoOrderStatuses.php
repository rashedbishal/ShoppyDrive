<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CourierEvidenceSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierVerificationReadinessService;
use App\Services\PathaoCourierService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[Signature('pathao:sync-statuses')]
#[Description('Poll Pathao for status updates on orders with an active consignment and sync order status accordingly.')]
class SyncPathaoOrderStatuses extends Command
{
    /**
     * BE-011: set while every Pathao status check is failing (outage, wrong
     * credentials, missing settings), shown on the admin dashboard and
     * cleared by the next run with at least one successful check. Plain
     * scalars only: the file cache store can't hold objects
     * (cache.serializable_classes is false).
     */
    public const PROBLEM_CACHE_KEY = 'courier:pathao-sync:problem';

    /** Stop a run after this many failures in a row with no success yet. */
    public const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * @return array{since: int, last_failed_at: int, last_error: string}|null
     */
    public static function currentProblem(): ?array
    {
        $problem = Cache::get(self::PROBLEM_CACHE_KEY);

        return is_array($problem) && is_int($problem['since'] ?? null) ? $problem : null;
    }

    /**
     * Execute the console command.
     */
    public function handle(PathaoCourierService $pathao, CourierVerificationReadinessService $readiness): int
    {
        // PAY-RETURN-001: the release this run is executing, recorded on
        // completion as the readiness scheduler marker.
        $startedAt = now();
        $fingerprint = $readiness->liveFingerprint()['fingerprint'];

        $orders = Order::query()
            ->whereNotNull('pathao_consignment_id')
            ->whereNotIn('status', [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->get();

        $succeeded = 0;
        $consecutiveFailures = 0;
        $lastError = null;

        foreach ($orders as $order) {
            try {
                $info = $pathao->getOrderInfo($order->pathao_consignment_id);
                $succeeded++;
                $consecutiveFailures = 0;
            } catch (RuntimeException|ConnectionException $e) {
                $consecutiveFailures++;
                $lastError = $e->getMessage();
                // ConnectionException (timeout/DNS/refused) is a separate
                // hierarchy from RuntimeException — without catching it
                // here too, one unreachable request would abort this
                // foreach entirely, skipping every other order in the
                // batch rather than just the one that failed.
                $this->warn("Order {$order->order_number}: {$e->getMessage()}");

                // BE-011: a login/configuration problem or an outage fails
                // every request the same way; stop instead of hammering
                // Pathao once per order every 15 minutes.
                if ($succeeded === 0 && $consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                    $this->error('Stopping: the first '.self::MAX_CONSECUTIVE_FAILURES.' Pathao checks all failed.');

                    break;
                }

                continue;
            }

            try {
                CourierOrderStatusMapper::sync($order, 'pathao', $info['order_status_slug'] ?? $info['order_status'], source: CourierEvidenceSource::PATHAO_POLL);
            } catch (Throwable $e) {
                // sync() rolls the whole status change back when one of its
                // financial side effects fails, so the order is retried on
                // the next run — but an order that keeps failing must not
                // abort this foreach and starve every order after it.
                report($e);
                $this->warn("Order {$order->order_number}: sync failed and was rolled back — {$e->getMessage()}");
            }
        }

        // BE-011: a run where every check failed is a failure. It is shown on
        // the dashboard and, by owner decision, is not recorded as a healthy
        // scheduler run for the courier readiness check.
        if ($orders->isNotEmpty() && $succeeded === 0) {
            $previous = self::currentProblem();
            Cache::forever(self::PROBLEM_CACHE_KEY, [
                'since' => $previous['since'] ?? now()->getTimestamp(),
                'last_failed_at' => now()->getTimestamp(),
                'last_error' => mb_substr((string) $lastError, 0, 300),
            ]);
            Log::error('[Pathao] Every status check in this run failed', [
                'orders' => $orders->count(),
                'last_error' => $lastError,
            ]);
            $this->error('Every Pathao status check failed. Last error: '.$lastError);

            return self::FAILURE;
        }

        Cache::forget(self::PROBLEM_CACHE_KEY);

        $this->info("Synced {$succeeded} of {$orders->count()} Pathao order(s).");

        $readiness->recordSchedulerCompletion($fingerprint, $startedAt);

        return self::SUCCESS;
    }
}
