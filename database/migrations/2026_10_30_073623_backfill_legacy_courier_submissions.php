<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Records every consignment already stored on an order as a SENT
     * submission, so the attempt history starts complete. An order carrying
     * both a Pathao and a Steadfast ID gets one row per courier — neither is
     * treated as primary, and both are inactive (`active_order_id` NULL), so
     * they can never collide with the one-unresolved-attempt constraint.
     * Orders are left untouched, soft-deleted ones included, and re-running
     * inserts nothing twice.
     */
    public function up(): void
    {
        $this->backfill('pathao', 'pathao_consignment_id', null, 'pathao_sent_at');
        $this->backfill('steadfast', 'steadfast_consignment_id', 'steadfast_tracking_code', 'steadfast_sent_at');
    }

    public function down(): void
    {
        DB::table('courier_submissions')->where('source', 'legacy_backfill')->delete();
    }

    private function backfill(string $courier, string $consignmentColumn, ?string $trackingColumn, string $sentAtColumn): void
    {
        $now = now();

        $legacyOrders = DB::table('orders')
            ->whereNotNull($consignmentColumn)
            ->where($consignmentColumn, '!=', '')
            ->whereNotExists(fn (Builder $query) => $query
                ->select(DB::raw(1))
                ->from('courier_submissions')
                ->whereColumn('courier_submissions.order_id', 'orders.id')
                ->where('courier_submissions.courier', $courier)
                ->whereColumn('courier_submissions.consignment_id', "orders.{$consignmentColumn}"))
            ->select([
                'id',
                DB::raw($this->quote($courier)),
                DB::raw("'sent'"),
                DB::raw("'legacy_backfill'"),
                'order_number',
                $consignmentColumn,
                $trackingColumn !== null ? $trackingColumn : DB::raw('NULL'),
                $sentAtColumn,
                $sentAtColumn,
                DB::raw($this->quote((string) $now)),
                DB::raw($this->quote((string) $now)),
            ]);

        DB::table('courier_submissions')->insertUsing([
            'order_id', 'courier', 'state', 'source', 'reference', 'consignment_id',
            'tracking_code', 'claimed_at', 'finished_at', 'created_at', 'updated_at',
        ], $legacyOrders);
    }

    private function quote(string $value): string
    {
        return DB::connection()->getPdo()->quote($value);
    }
};
