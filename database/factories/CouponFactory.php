<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE-####')),
            'name' => fake()->words(2, true),
            'type' => CouponType::PERCENT,
            'value' => 10,
            'min_order_amount' => null,
            'max_uses' => null,
            'used_count' => 0,
            'per_user_limit' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
