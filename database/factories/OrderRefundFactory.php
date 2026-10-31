<?php

namespace Database\Factories;

use App\Models\OrderRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRefund>
 */
class OrderRefundFactory extends Factory
{
    /**
     * There is no Order factory, so always pass order_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => OrderRefund::SOURCE_ORDER,
            'amount' => 100,
            'method' => 'bkash',
            'is_backfilled' => false,
        ];
    }

    public function backfilled(): static
    {
        return $this->state(fn () => ['source' => OrderRefund::SOURCE_RETURN, 'is_backfilled' => true]);
    }
}
