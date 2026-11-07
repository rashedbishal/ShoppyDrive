<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CourierEvidenceSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CourierOrderStatusMapper;
use App\Services\SteadfastCourierService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * BIZ-027: a fallback for the Steadfast webhook, whose real format has never
 * been proven. A status CourierOrderStatusMapper doesn't recognise is recorded
 * on the order and flagged, but never moves it, so a wrong guess here can't
 * mark anything delivered or cancelled.
 */
#[Signature('steadfast:sync-statuses')]
#[Description('Poll Steadfast for status updates on orders with an active consignment and sync order status accordingly.')]
class SyncSteadfastOrderStatuses extends Command
{
    /** Same shape and reason as SyncPathaoOrderStatuses::PROBLEM_CACHE_KEY. */
    public const PROBLEM_CACHE_KEY = 'courier:steadfast-sync:problem';

    /** Stop a run after this many failures in a row with no success yet. */
    public const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * BE-004: keeps a run well inside its 30-minute overlap lock. Orders are
     * shuffled, so what one run skips isn't always the same.
     */
    public const MAX_RUN_SECONDS = 600;

    /** A parcel the courier never closes shouldn't be polled forever. */
    public const MAX_ORDER_AGE_DAYS = 60;

    /**
     * @return array{since: int, last_failed_at: int, last_error: string}|null
     */
    public static function currentProblem(): ?array
    {
        $problem = Cache::get(self::PROBLEM_CACHE_KEY);

        return is_array($problem) && is_int($problem['since'] ?? null) ? $problem : null;
    }

    public function handle(SteadfastCourierService $steadfast): int
    {
        $orders = Order::query()
            ->whereNotNull('steadfast_consignment_id')
            ->whereNotIn('status', [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->where('created_at', '>=', now()->subDays(self::MAX_ORDER_AGE_DAYS))
            ->get();

        $succeeded = 0;
        $consecutiveFailures = 0;
        $lastError = null;

        $stopAt = now()->addSeconds(self::MAX_RUN_SECONDS);

        foreach ($orders->shuffle() as $order) {
            if (now()->greaterThanOrEqualTo($stopAt)) {
                $this->warn('Stopping: reached the time limit for one run.');

                break;
            }

            try {
                $info = $steadfast->getOrderInfo($order->steadfast_consignment_id);
                $succeeded++;
                $consecutiveFailures = 0;
            } catch (RuntimeException|ConnectionException $e) {
                $consecutiveFailures++;
                $lastError = $e->getMessage();
                $this->warn("Order {$order->order_number}: {$e->getMessage()}");

                if ($succeeded === 0 && $consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                    $this->error('Stopping: the first '.self::MAX_CONSECUTIVE_FAILURES.' Steadfast checks all failed.');

                    break;
                }

                continue;
            }

            try {
                CourierOrderStatusMapper::sync($order, 'steadfast', $info['status'], source: CourierEvidenceSource::STEADFAST_POLL);
            } catch (Throwable $e) {
                // One order that keeps failing must not starve the rest; sync()
                // rolled it back, so the next run retries it.
                report($e);
                $this->warn("Order {$order->order_number}: sync failed and was rolled back — {$e->getMessage()}");
            }
        }

        if ($orders->isNotEmpty() && $succeeded === 0) {
            $previous = self::currentProblem();
            Cache::forever(self::PROBLEM_CACHE_KEY, [
                'since' => $previous['since'] ?? now()->getTimestamp(),
                'last_failed_at' => now()->getTimestamp(),
                'last_error' => mb_substr((string) $lastError, 0, 300),
            ]);
            Log::error('[Steadfast] Every status check in this run failed', [
                'orders' => $orders->count(),
                'last_error' => $lastError,
            ]);
            $this->error('Every Steadfast status check failed. Last error: '.$lastError);

            return self::FAILURE;
        }

        Cache::forget(self::PROBLEM_CACHE_KEY);

        $this->info("Synced {$succeeded} of {$orders->count()} Steadfast order(s).");

        return self::SUCCESS;
    }
}
