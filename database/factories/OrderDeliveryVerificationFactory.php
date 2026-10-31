<?php

namespace Database\Factories;

use App\Models\OrderDeliveryVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDeliveryVerification>
 */
class OrderDeliveryVerificationFactory extends Factory
{
    /**
     * Raw rows for tests. To be non-stale a verification must carry the
     * order's current snapshot — use verifyConsignmentForTest() in
     * tests/Pest.php, which takes it from CourierEvidenceChain.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'target_type' => OrderDeliveryVerification::TARGET_CONSIGNMENT,
            'provider' => 'steadfast',
            'outcome' => OrderDeliveryVerification::OUTCOME_FULL_DELIVERY,
            'timeline_reviewed' => true,
            'evidence_max_id' => 0,
            'history_max_id' => 0,
            'submission_state_snapshot' => [],
            'chain_snapshot' => [],
            'admin_name' => 'Mother Admin',
            'note' => 'Verified in the courier portal for tests.',
        ];
    }
}
