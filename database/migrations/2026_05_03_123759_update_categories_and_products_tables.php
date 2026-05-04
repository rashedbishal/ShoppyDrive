<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add sub_category_id to products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('sub_category_id')->nullable()->after('category_id')->constrained('sub_categories')->cascadeOnDelete();
        });

        // 2. Data Migration: Move sub-categories from categories table to sub_categories table
        $subCategories = DB::table('categories')->whereNotNull('parent_id')->get();

        foreach ($subCategories as $subCategory) {
            // Insert into new sub_categories table
            $newSubCategoryId = DB::table('sub_categories')->insertGetId([
                'name' => $subCategory->name,
                'slug' => $subCategory->slug,
                'category_id' => $subCategory->parent_id,
                'image' => $subCategory->image,
                'description' => $subCategory->description,
                'meta_title' => $subCategory->meta_title,
                'meta_description' => $subCategory->meta_description,
                'is_active' => $subCategory->is_active,
                'sort_order' => $subCategory->sort_order,
                'created_at' => $subCategory->created_at,
                'updated_at' => $subCategory->updated_at,
                'deleted_at' => $subCategory->deleted_at,
            ]);

            // Update products that point to this sub-category
            // Old setup: products.category_id pointed to the sub-category (which was in categories table)
            // We update: products.sub_category_id = new sub category ID, products.category_id = parent category ID
            DB::table('products')
                ->where('category_id', $subCategory->id)
                ->update([
                    'sub_category_id' => $newSubCategoryId,
                    'category_id' => $subCategory->parent_id,
                ]);
        }

        // 3. Delete old sub-categories from categories table
        DB::table('categories')->whereNotNull('parent_id')->delete();

        // 4. Drop parent_id from categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
        });

        // Note: Down migration doesn't perfectly restore data if sub_categories have changed,
        // but this handles the schema reversal.
        $subCategories = DB::table('sub_categories')->get();
        foreach ($subCategories as $subCategory) {
            $oldCategoryId = DB::table('categories')->insertGetId([
                'name' => $subCategory->name,
                'slug' => $subCategory->slug,
                'parent_id' => $subCategory->category_id,
                'image' => $subCategory->image,
                'description' => $subCategory->description,
                'meta_title' => $subCategory->meta_title,
                'meta_description' => $subCategory->meta_description,
                'is_active' => $subCategory->is_active,
                'sort_order' => $subCategory->sort_order,
                'created_at' => $subCategory->created_at,
                'updated_at' => $subCategory->updated_at,
                'deleted_at' => $subCategory->deleted_at,
            ]);

            DB::table('products')
                ->where('sub_category_id', $subCategory->id)
                ->update([
                    'category_id' => $oldCategoryId,
                ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['sub_category_id']);
            $table->dropColumn('sub_category_id');
        });
    }
};
