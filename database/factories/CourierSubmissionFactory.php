<?php

namespace Database\Factories;

use App\Enums\CourierSubmissionState;
use App\Models\CourierSubmission;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourierSubmission>
 */
class CourierSubmissionFactory extends Factory
{
    /**
     * A fresh, unresolved Steadfast attempt. There is no Order factory, so
     * always build through forOrder().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'courier' => 'steadfast',
            'state' => CourierSubmissionState::SUBMITTING,
            'source' => 'app',
            'claimed_at' => now(),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'active_order_id' => $order->id,
            'reference' => $order->order_number,
        ]);
    }

    public function unknown(): static
    {
        return $this->state(fn () => [
            'state' => CourierSubmissionState::UNKNOWN,
            'failure_kind' => 'connection_interrupted',
            'finished_at' => now(),
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn () => ['claimed_at' => now()->subMinutes(CourierSubmission::STALE_AFTER_MINUTES + 1)]);
    }
}
