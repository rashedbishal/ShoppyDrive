<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the loyalty points held by an unpaid pending checkout, and
     * backfills it for checkouts that can still be paid so they keep their
     * discount covered across the deploy. Expired and converted rows never
     * hold points, so they stay null.
     */
    public function up(): void
    {
        Schema::table('pending_checkouts', function (Blueprint $table): void {
            $table->unsignedInteger('points_reserved')->nullable()->after('checkout_data');
        });

        DB::table('pending_checkouts')
            ->whereNotNull('user_id')
            ->whereNull('order_number')
            ->where('expires_at', '>=', now())
            ->select(['id', 'checkout_data'])
            ->chunkById(200, function ($pendingCheckouts): void {
                foreach ($pendingCheckouts as $pendingCheckout) {
                    $points = (int) (json_decode($pendingCheckout->checkout_data, true)['points_redeemed'] ?? 0);

                    if ($points > 0) {
                        DB::table('pending_checkouts')
                            ->where('id', $pendingCheckout->id)
                            ->update(['points_reserved' => $points]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('pending_checkouts', function (Blueprint $table): void {
            $table->dropColumn('points_reserved');
        });
    }
};
