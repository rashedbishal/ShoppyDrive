<?php

namespace Database\Factories;

use App\Models\BlockedIp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedIp>
 */
class BlockedIpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address' => fake()->unique()->ipv4(),
            'reason' => fake()->sentence(),
            'blocked_by' => null,
        ];
    }
}
