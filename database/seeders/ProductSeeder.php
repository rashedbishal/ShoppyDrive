<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubCategory::all()->each(function (SubCategory $subCategory) {
            Product::factory()->count(5)->create([
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
            ]);
        });
    }
}
