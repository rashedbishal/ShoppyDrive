<?php

namespace Database\Factories;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSaleItem>
 */
class FlashSaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flash_sale_id' => FlashSale::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'sale_price' => fake()->randomFloat(2, 10, 500),
            'stock_limit' => null,
            'sold_count' => 0,
        ];
    }
}
