<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Meta (Facebook) Conversions API event — the outbox the
 * SendMetaTrackingEventJob delivers from. The unique (event_name, event_id)
 * pair is the deduplication guarantee: a repeated browser request or a
 * second order hook can never create a second logical event. Raw email and
 * phone are never stored here; they're read from the order/account and
 * hashed only when the event is sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 50);
            $table->string('event_id', 100);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_source', 20)->default('website');
            $table->text('event_source_url')->nullable();
            $table->json('custom_data');
            $table->string('client_ip_address', 45)->nullable();
            $table->text('client_user_agent')->nullable();
            $table->string('fbp')->nullable();
            $table->string('fbc')->nullable();
            $table->timestamp('event_time');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_name', 'event_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_tracking_events');
    }
};
