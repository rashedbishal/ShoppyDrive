<?php

namespace Database\Factories;

use App\Models\IncompleteOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IncompleteOrder>
 */
class IncompleteOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();

        $price = (float) $product->price;
        $quantity = fake()->numberBetween(1, 3);

        $items = [[
            'product_id' => $product->id,
            'variant_id' => null,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'image' => null,
            'variant_attributes' => null,
        ]];

        return [
            'attempt_token' => (string) Str::uuid(),
            'user_id' => null,
            'name' => fake()->optional()->name(),
            'phone' => '01'.fake()->numberBetween(3, 9).fake()->numerify('########'),
            'items' => $items,
            'subtotal' => $items[0]['price'] * $items[0]['quantity'],
        ];
    }
}
