<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * COURIER-SEND-RACE-002: read-only preflight for the unique indexes on
 * `orders.pathao_consignment_id` / `orders.steadfast_consignment_id`.
 * Run by `php artisan courier:check-consignment-duplicates` before a deploy,
 * and by the index migration itself, which refuses to proceed (and changes
 * nothing) when this finds anything. Soft-deleted orders are included —
 * a unique index covers them too — and so is the empty string, which an
 * old "success without consignment" response could have stored and which,
 * unlike NULL, a unique index does not allow twice. Never modifies data.
 */
class ConsignmentDuplicateCheck
{
    public const COLUMNS = ['pathao_consignment_id', 'steadfast_consignment_id'];

    /**
     * @return array<string, list<array{consignment_id: string, orders: int, order_ids: list<int>}>>
     */
    public static function find(): array
    {
        $duplicates = [];

        foreach (self::COLUMNS as $column) {
            $values = DB::table('orders')
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->orderBy($column)
                ->pluck($column);

            foreach ($values as $value) {
                $orderIds = DB::table('orders')->where($column, $value)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

                $duplicates[$column][] = [
                    'consignment_id' => (string) $value,
                    'orders' => count($orderIds),
                    'order_ids' => $orderIds,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<string, list<array{consignment_id: string, orders: int, order_ids: list<int>}>>  $duplicates
     */
    public static function describe(array $duplicates): string
    {
        $lines = [];

        foreach ($duplicates as $column => $rows) {
            foreach ($rows as $row) {
                $shown = $row['consignment_id'] === '' ? '(empty string)' : $row['consignment_id'];
                $lines[] = "orders.{$column} = {$shown} on {$row['orders']} orders (ids ".implode(', ', $row['order_ids']).')';
            }
        }

        return implode("\n", $lines);
    }
}
