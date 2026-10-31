<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Courier mapper notes cannot have been written by the system before this (first deploy containing de9fe0e). */
    private const MAPPER_EARLIEST = '2026-10-18 12:40:16';

    /** The legacy Steadfast webhook note format cannot have been written by the system before this (deploy of 52fb4dc). */
    private const LEGACY_WEBHOOK_EARLIEST = '2026-10-18 10:19:12';

    /**
     * PAY-RETURN-001 (verification-only mode): structured courier evidence,
     * the mother admin's audited consignment/submission verifications, and
     * the operational readiness attestation every verification depends on.
     * Created in dependency order so every foreign key references an existing
     * table. All three are append-only in the application.
     */
    public function up(): void
    {
        Schema::create('courier_verification_readiness', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20); // accepted | refused
            $table->json('refusal_reasons')->nullable();
            $table->string('fingerprint', 64);
            $table->string('local_fingerprint', 64)->nullable();
            $table->unsignedInteger('fingerprint_file_count')->nullable();
            $table->string('deploy_run_id', 40)->nullable();
            $table->string('deploy_sha', 40)->nullable();
            $table->timestamp('deploy_run_started_at')->nullable();
            $table->timestamp('deploy_run_completed_at')->nullable();
            $table->unsignedBigInteger('config_cache_mtime')->nullable();
            $table->unsignedBigInteger('route_cache_mtime')->nullable();
            $table->timestamp('web_restarted_at')->nullable();
            $table->json('opcache_probe')->nullable();
            $table->json('scheduler_marker')->nullable();
            $table->text('process_list')->nullable();
            $table->json('checklist');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('fingerprint');
        });

        Schema::create('courier_delivery_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('consignment_id', 100)->nullable();
            $table->foreignId('courier_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_status_history_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('evidence_type', 20);
            $table->string('anchor_kind', 20)->nullable();
            $table->string('source', 30);
            $table->unsignedInteger('code_generation')->default(1);
            $table->string('previous_raw_status')->nullable();
            $table->string('raw_status')->nullable();
            $table->string('normalized_status')->nullable();
            $table->string('mapped_order_status', 20)->nullable();
            $table->string('order_status_before', 20)->nullable();
            $table->string('order_status_after', 20)->nullable();
            $table->boolean('is_partial')->default(false);
            $table->boolean('is_full_delivery')->default(false);
            $table->boolean('anchor_clean')->nullable();
            $table->unsignedBigInteger('history_watermark_id')->nullable();
            $table->string('gap_reason', 40)->nullable();
            $table->string('anchor_key', 140)->nullable()->unique();
            $table->string('gap_key', 140)->nullable()->unique();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'provider', 'consignment_id', 'id'], 'courier_evidence_chain_index');
            $table->index(['order_id', 'is_partial']);
        });

        Schema::create('order_delivery_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('target_type', 20); // consignment | submission
            $table->string('provider', 20);
            $table->string('consignment_id', 100)->nullable();
            $table->foreignId('courier_submission_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('outcome', 20); // full_delivery | not_delivered | not_created
            $table->decimal('verified_collected_amount', 10, 2)->nullable();
            $table->boolean('timeline_reviewed')->default(false);
            $table->unsignedBigInteger('evidence_max_id')->default(0);
            $table->unsignedBigInteger('history_max_id')->default(0);
            $table->string('raw_status_at_verification')->nullable();
            $table->json('submission_state_snapshot');
            $table->json('chain_snapshot');
            $table->foreignId('readiness_id')->constrained('courier_verification_readiness')->restrictOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->text('note');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'provider', 'consignment_id', 'id'], 'delivery_verification_target_index');
        });

        $this->backfillLegacyPartialEvidence();
    }

    public function down(): void
    {
        if (DB::table('courier_delivery_evidence')->where('source', '<>', 'legacy_backfill')->exists()
            || DB::table('order_delivery_verifications')->exists()
            || DB::table('courier_verification_readiness')->exists()) {
            throw new RuntimeException('Refusing to roll back: courier evidence, delivery verifications or readiness attestations recorded by the application would be erased.');
        }

        Schema::dropIfExists('order_delivery_verifications');
        Schema::dropIfExists('courier_delivery_evidence');
        Schema::dropIfExists('courier_verification_readiness');
    }

    /**
     * Partial-delivery evidence only from system-written sources: the raw
     * courier status columns, mapper report notes, and the exact legacy
     * Steadfast webhook format — never arbitrary admin free text. These rows
     * can only ever block a refund. Done in PHP (preg_match) so it behaves
     * identically on SQLite and MySQL.
     */
    private function backfillLegacyPartialEvidence(): void
    {
        $now = now();

        foreach (['pathao' => 'pathao_order_status', 'steadfast' => 'steadfast_status'] as $provider => $column) {
            DB::table('orders')
                ->whereRaw("LOWER(COALESCE({$column}, '')) LIKE '%partial%'")
                ->orderBy('id')
                ->select(['id', $column, "{$provider}_consignment_id as consignment_id"])
                ->chunk(200, function ($orders) use ($provider, $column, $now): void {
                    foreach ($orders as $order) {
                        DB::table('courier_delivery_evidence')->insert($this->legacyRow($order->id, $provider, $order->consignment_id, $order->{$column}, null, $now));
                    }
                });
        }

        DB::table('order_status_histories')
            ->whereRaw("LOWER(COALESCE(notes, '')) LIKE '%partial%'")
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    $created = (string) $row->created_at;

                    if ($created >= self::MAPPER_EARLIEST && preg_match('/^(Pathao|Steadfast) reported "([^"]*partial[^"]*)"/i', (string) $row->notes, $m) === 1) {
                        $provider = strtolower($m[1]);
                    } elseif ($created >= self::LEGACY_WEBHOOK_EARLIEST && preg_match('/^Steadfast status updated to "([^"]*partial[^"]*)"\./i', (string) $row->notes, $m) === 1) {
                        $provider = 'steadfast';
                        $m[2] = $m[1];
                    } else {
                        continue;
                    }

                    $consignment = DB::table('orders')->where('id', $row->order_id)->value("{$provider}_consignment_id");
                    DB::table('courier_delivery_evidence')->insert($this->legacyRow($row->order_id, $provider, $consignment, $m[2], $row->id, $now));
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyRow(int $orderId, string $provider, ?string $consignmentId, string $rawStatus, ?int $historyId, mixed $now): array
    {
        return [
            'order_id' => $orderId,
            'provider' => $provider,
            'consignment_id' => $consignmentId,
            'order_status_history_id' => $historyId,
            'evidence_type' => 'legacy_partial',
            'source' => 'legacy_backfill',
            'code_generation' => 1,
            'raw_status' => $rawStatus,
            'normalized_status' => strtolower(str_replace([' ', '-'], '_', trim($rawStatus))),
            'is_partial' => true,
            'received_at' => $now,
            'created_at' => $now,
        ];
    }
};
