<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two received_sms rows sharing the same (provider, parsed_transaction_id)
     * represent the same real-world mobile-banking transaction (e.g. the SMS
     * forwarder retried delivery). Before this migration, each row could be
     * claimed independently, letting one transaction ID pay for two separate
     * orders. Collapse existing duplicates (keeping the matched row if one of
     * them was already consumed, otherwise the earliest) before adding the
     * unique constraint that prevents this going forward.
     */
    public function up(): void
    {
        DB::table('received_sms')
            ->select('provider', 'parsed_transaction_id')
            ->whereNotNull('parsed_transaction_id')
            ->groupBy('provider', 'parsed_transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $keepId = DB::table('received_sms')
                    ->where('provider', $duplicate->provider)
                    ->where('parsed_transaction_id', $duplicate->parsed_transaction_id)
                    ->orderByDesc('is_matched')
                    ->orderBy('id')
                    ->value('id');

                DB::table('received_sms')
                    ->where('provider', $duplicate->provider)
                    ->where('parsed_transaction_id', $duplicate->parsed_transaction_id)
                    ->where('id', '!=', $keepId)
                    ->delete();
            });

        Schema::table('received_sms', function (Blueprint $table) {
            $table->unique(['provider', 'parsed_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('received_sms', function (Blueprint $table) {
            $table->dropUnique(['provider', 'parsed_transaction_id']);
        });
    }
};
