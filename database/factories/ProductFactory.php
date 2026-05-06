<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => fake()->unique()->bothify('??-#####'),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'sub_category_id' => SubCategory::factory(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'compare_price' => fake()->optional()->randomFloat(2, 1000, 2000),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'weight' => fake()->randomFloat(2, 0, 10),
        ];
    }
}
