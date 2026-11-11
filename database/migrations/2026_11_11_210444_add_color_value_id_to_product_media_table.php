<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product images are chosen per colour, not per colour/size pair: an image
     * with `color_value_id` belongs to every variant of that colour (see
     * ProductVariant::getImageAttribute()). Existing images tied to a coloured
     * variant get that colour, so a Red/L photo now also serves Red/XL.
     * `variant_id` is left exactly as it was, so nothing is lost and `down()`
     * restores the previous state.
     */
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->foreignId('color_value_id')->nullable()->after('variant_id')->constrained('attribute_values')->nullOnDelete();
        });

        DB::table('product_media')
            ->whereNotNull('variant_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $colors = DB::table('product_variants')
                    ->whereIn('id', $rows->pluck('variant_id')->unique()->all())
                    ->whereNotNull('color_value_id')
                    ->pluck('color_value_id', 'id');

                foreach ($rows as $row) {
                    if (isset($colors[$row->variant_id])) {
                        DB::table('product_media')->where('id', $row->id)->update(['color_value_id' => $colors[$row->variant_id]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_value_id');
        });
    }
};
