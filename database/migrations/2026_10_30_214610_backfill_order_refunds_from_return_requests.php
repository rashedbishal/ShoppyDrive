<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PAY-RETURN-001: every refund already recorded on a return request
     * counts toward the new cumulative cap. Only real recorded amounts are
     * copied — nothing is invented (whole-order refunds before this release
     * never recorded an amount, so they are not backfilled; those orders are
     * already REFUNDED, which refuses any further refund). Idempotent.
     */
    public function up(): void
    {
        DB::table('return_requests')
            ->where('status', 'refunded')
            ->whereNotNull('refund_amount')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('order_refunds')->whereColumn('order_refunds.return_request_id', 'return_requests.id'))
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                DB::table('order_refunds')->insert($rows->map(fn ($row): array => [
                    'order_id' => $row->order_id,
                    'return_request_id' => $row->id,
                    'source' => 'return',
                    'amount' => $row->refund_amount,
                    'method' => $row->refund_method,
                    'is_backfilled' => true,
                    'note' => 'Backfilled from the return request recorded before PAY-RETURN-001.',
                    'created_at' => $row->resolved_at ?? $row->updated_at,
                ])->all());
            });
    }

    public function down(): void
    {
        DB::table('order_refunds')->where('is_backfilled', true)->delete();
    }
};
