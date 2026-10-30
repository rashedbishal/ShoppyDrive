<?php

use App\Support\ConsignmentDuplicateCheck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COURIER-SEND-RACE-002 (M2): one consignment ID can belong to only one
     * order. Unique indexes allow any number of NULLs on MySQL, MariaDB and
     * SQLite, so unsent orders are unaffected. The plain indexes these
     * replace are dropped (the unique index serves the same lookups).
     *
     * Preflight: if existing data already violates this, the migration stops
     * before changing anything and lists the offending orders — it never
     * edits or deletes order data. `php artisan courier:check-consignment-duplicates`
     * runs the same check read-only ahead of a deploy.
     */
    public function up(): void
    {
        $duplicates = ConsignmentDuplicateCheck::find();

        if ($duplicates !== []) {
            throw new RuntimeException("Cannot add unique consignment indexes: these consignment IDs are shared by more than one order. Nothing was changed. Resolve them manually, then run the migration again.\n".ConsignmentDuplicateCheck::describe($duplicates));
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['pathao_consignment_id']);
            $table->dropIndex(['steadfast_consignment_id']);
            $table->unique('pathao_consignment_id');
            $table->unique('steadfast_consignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['pathao_consignment_id']);
            $table->dropUnique(['steadfast_consignment_id']);
            $table->index('pathao_consignment_id');
            $table->index('steadfast_consignment_id');
        });
    }
};
