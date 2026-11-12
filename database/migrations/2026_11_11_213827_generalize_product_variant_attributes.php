<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variants are no longer limited to Color and Size: a product picks any
 * attributes (Set, Material, …) in order (`products.variant_attribute_ids`),
 * and each variant records its value for each one
 * (`product_variants.attribute_value_ids`, in that order). An attribute can
 * allow images per value (`attributes.has_images`), and an image chosen for a
 * value is `product_media.attribute_value_id` (replacing the colour-only
 * `color_value_id` added just before this, not yet deployed).
 *
 * Existing data is carried over: Color/Size products get [Color, Size], each
 * variant its colour/size values, Color allows images, and so does Size where
 * an existing image was tied to a size-only variant, so that link survives.
 * The old `color_value_id`/`size_value_id`/`has_*_variant` columns are kept
 * (and still written for Color and Size) so a rollback finds them current.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            // Names are kept unique by the admin form (AttributeRequest), not
            // an index: existing databases and test fixtures may already hold
            // a second "Color" created outside it.
            $table->boolean('has_images')->default(false)->after('type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('variant_attribute_ids')->nullable()->after('has_size_variant');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('attribute_value_ids')->nullable()->after('size_value_id');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->foreignId('attribute_value_id')->nullable()->after('variant_id')->constrained('attribute_values')->nullOnDelete();
        });

        $colorId = DB::table('attributes')->where('name', 'Color')->value('id');
        $sizeId = DB::table('attributes')->where('name', 'Size')->value('id');

        if ($colorId !== null) {
            DB::table('attributes')->where('id', $colorId)->update(['has_images' => true]);
        }

        $sizeImagesExist = $sizeId !== null && DB::table('product_media')
            ->join('product_variants', 'product_variants.id', '=', 'product_media.variant_id')
            ->whereNull('product_variants.color_value_id')
            ->whereNotNull('product_variants.size_value_id')
            ->exists();

        if ($sizeImagesExist) {
            DB::table('attributes')->where('id', $sizeId)->update(['has_images' => true]);
        }

        DB::table('products')
            ->where(fn ($query) => $query->where('has_color_variant', true)->orWhere('has_size_variant', true))
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($colorId, $sizeId): void {
                foreach ($products as $product) {
                    $ids = array_values(array_filter([
                        $product->has_color_variant ? $colorId : null,
                        $product->has_size_variant ? $sizeId : null,
                    ]));

                    DB::table('products')->where('id', $product->id)->update(['variant_attribute_ids' => json_encode($ids)]);
                }
            });

        DB::table('product_variants')
            ->where(fn ($query) => $query->whereNotNull('color_value_id')->orWhereNotNull('size_value_id'))
            ->orderBy('id')
            ->chunkById(500, function ($variants): void {
                foreach ($variants as $variant) {
                    $ids = array_values(array_filter([$variant->color_value_id, $variant->size_value_id]));

                    DB::table('product_variants')->where('id', $variant->id)->update(['attribute_value_ids' => json_encode($ids)]);
                }
            });

        // The colour an image was chosen for; otherwise (size-only variant,
        // Size now allowing images) the size it was tied to.
        DB::table('product_media')
            ->where(fn ($query) => $query->whereNotNull('color_value_id')->orWhereNotNull('variant_id'))
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($sizeImagesExist): void {
                $variants = DB::table('product_variants')
                    ->whereIn('id', $rows->pluck('variant_id')->filter()->unique()->all())
                    ->get(['id', 'color_value_id', 'size_value_id'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $variant = $variants[$row->variant_id] ?? null;
                    $valueId = $row->color_value_id
                        ?? $variant?->color_value_id
                        ?? ($sizeImagesExist ? $variant?->size_value_id : null);

                    if ($valueId !== null) {
                        DB::table('product_media')->where('id', $row->id)->update(['attribute_value_id' => $valueId]);
                    }
                }
            });

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_value_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->foreignId('color_value_id')->nullable()->after('variant_id')->constrained('attribute_values')->nullOnDelete();
        });

        $colorId = DB::table('attributes')->where('name', 'Color')->value('id');

        if ($colorId !== null) {
            DB::table('product_media')
                ->whereIn('attribute_value_id', DB::table('attribute_values')->where('attribute_id', $colorId)->select('id'))
                ->update(['color_value_id' => DB::raw('attribute_value_id')]);
        }

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attribute_value_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('attribute_value_ids');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variant_attribute_ids');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('has_images');
        });
    }
};
