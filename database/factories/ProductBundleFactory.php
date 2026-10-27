<?php

namespace Database\Factories;

use App\Enums\BundleDiscountType;
use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductBundle>
 */
class ProductBundleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'description' => fake()->sentence(),
            'image' => '/placeholder.svg',
            'discount_type' => BundleDiscountType::FixedPrice,
            'bundle_price' => fake()->randomFloat(2, 100, 1000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function freeDelivery(): static
    {
        return $this->state([
            'discount_type' => BundleDiscountType::FreeDelivery,
            'bundle_price' => null,
        ]);
    }
}
