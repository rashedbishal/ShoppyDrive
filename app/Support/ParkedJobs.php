<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Commands\DrainQueueCommand;
use App\Models\Order;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Jobs sitting on a queue the worker never reads (owner request
 * 2026-11-14): the `held-backlog` jobs set aside on purpose, or any other
 * queue name that isn't in DrainQueueCommand::WORKER_QUEUES. They never run,
 * so Admin > System Monitor shows them apart from the jobs actually waiting,
 * and the mother admin can delete them there. Nothing here ever releases,
 * retries or moves a job.
 *
 * Everyone who can open the monitor sees each job's class name only. The
 * mother admin also gets a short summary (the order number and a masked
 * recipient email) so she can judge whether it is still worth sending; it is
 * read from the payload text with patterns, never unserialized, so reading
 * it can't run any code.
 */
final class ParkedJobs
{
    /** The most the monitor lists; the count is always exact. */
    public const LIST_LIMIT = 100;

    public static function count(): int
    {
        return self::query()->count();
    }

    /**
     * @return list<array{id: int, queue: string, name: string, attempts: int, created_at: int, summary: string|null}>
     */
    public static function list(bool $withSummary = false): array
    {
        $jobs = self::query()
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'queue', 'payload', 'attempts', 'created_at']);

        $references = $withSummary
            ? $jobs->map(fn (object $job): array => self::references((string) $job->payload))
            : collect();
        $orderNumbers = $withSummary
            ? Order::withTrashed()
                ->whereIn('id', $references->flatMap(fn (array $found): array => $found['orders'])->unique()->values()->all())
                ->pluck('order_number', 'id')
            : collect();

        return $jobs->values()->map(fn (object $job, int $index): array => [
            'id' => (int) $job->id,
            'queue' => (string) $job->queue,
            'name' => self::displayName((string) $job->payload),
            'attempts' => (int) $job->attempts,
            'created_at' => (int) $job->created_at,
            'summary' => $withSummary ? self::summary($references[$index], $orderNumbers->all()) : null,
        ])->all();
    }

    /**
     * The order ids and recipient addresses a queued job refers to, read from
     * the serialized text (Laravel's ModelIdentifier and a mailable's `to`).
     *
     * @return array{orders: list<int>, recipients: list<string>, encrypted: bool}
     */
    private static function references(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $command = is_array($decoded) ? (string) ($decoded['data']['command'] ?? '') : '';

        // An encrypted job's details can't be read without decrypting it.
        if ($command !== '' && ! str_starts_with($command, 'O:')) {
            return ['orders' => [], 'recipients' => [], 'encrypted' => true];
        }

        preg_match_all('/s:5:"class";s:\d+:"App\\\\Models\\\\Order";s:2:"id";i:(\d+);/', $command, $orders);
        preg_match_all('/s:7:"address";s:\d+:"([^"]+)"/', $command, $recipients);

        return [
            'orders' => array_values(array_unique(array_map('intval', $orders[1]))),
            'recipients' => array_values(array_unique($recipients[1])),
            'encrypted' => false,
        ];
    }

    /**
     * "Order ORD-1234 · to r***@gmail.com".
     *
     * @param  array{orders: list<int>, recipients: list<string>, encrypted: bool}  $found
     * @param  array<int, string>  $orderNumbers
     */
    private static function summary(array $found, array $orderNumbers): ?string
    {
        if ($found['encrypted']) {
            return 'Details are encrypted';
        }

        $parts = [];

        foreach ($found['orders'] as $orderId) {
            $parts[] = isset($orderNumbers[$orderId])
                ? 'Order '.$orderNumbers[$orderId]
                : 'Order #'.$orderId.' (no longer exists)';
        }

        if ($found['recipients'] !== []) {
            $parts[] = 'to '.implode(', ', array_map(self::maskEmail(...), $found['recipients']));
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** "rahim.uddin@gmail.com" → "r***@gmail.com". */
    private static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    /**
     * Deletes exactly the given jobs, and only while they are still parked
     * and not being run: an id that has since moved to a worker queue (or
     * never was parked) is left alone, so this can never remove a job the
     * shop is still going to send.
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, queue: string, name: string}> what was deleted
     */
    public static function delete(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::transaction(function () use ($ids): array {
            $jobs = self::query()
                ->whereIn('id', $ids)
                ->whereNull('reserved_at')
                ->lockForUpdate()
                ->get(['id', 'queue', 'payload']);

            if ($jobs->isEmpty()) {
                return [];
            }

            self::query()->whereIn('id', $jobs->pluck('id')->all())->whereNull('reserved_at')->delete();

            return $jobs->map(fn (object $job): array => [
                'id' => (int) $job->id,
                'queue' => (string) $job->queue,
                'name' => self::displayName((string) $job->payload),
            ])->values()->all();
        });
    }

    private static function query(): Builder
    {
        return DB::table((string) config('queue.connections.database.table', 'jobs'))
            ->whereNotIn('queue', DrainQueueCommand::WORKER_QUEUES);
    }

    /** "App\Mail\OrderPlacedMail" → "OrderPlacedMail"; the class name only. */
    private static function displayName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? (string) ($decoded['displayName'] ?? '') : '';

        if ($name === '') {
            return 'Unknown job';
        }

        return class_basename($name);
    }
}
