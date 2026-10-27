<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBundleItem>
 */
class ProductBundleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_bundle_id' => ProductBundle::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'quantity' => 1,
        ];
    }
}
