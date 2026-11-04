<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A product card now swaps images on hover only when one is explicitly
     * chosen. Existing products get the image their card already swaps to
     * (the first non-primary image), so nothing changes for them until an
     * admin decides otherwise.
     */
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->boolean('is_hover')->default(false)->after('is_primary');
        });

        DB::table('product_media')
            ->where('type', 'image')
            ->where('is_primary', false)
            ->selectRaw('MIN(id) as id')
            ->groupBy('product_id')
            ->pluck('id')
            ->chunk(500)
            ->each(fn ($ids) => DB::table('product_media')->whereIn('id', $ids->all())->update(['is_hover' => true]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('is_hover');
        });
    }
};
