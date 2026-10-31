<?php

namespace Database\Factories;

use App\Models\CourierVerificationReadiness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourierVerificationReadiness>
 */
class CourierVerificationReadinessFactory extends Factory
{
    /**
     * An accepted attestation. Whether it is *current* depends on the live
     * fingerprint and deployment marker — tests pin those with a fake
     * readiness service (see tests/Pest.php).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => CourierVerificationReadiness::STATUS_ACCEPTED,
            'fingerprint' => str_repeat('a', 64),
            'local_fingerprint' => str_repeat('a', 64),
            'fingerprint_file_count' => 1,
            'deploy_run_id' => '1',
            'deploy_sha' => str_repeat('b', 40),
            'deploy_run_started_at' => now()->subHour(),
            'deploy_run_completed_at' => now()->subMinutes(55),
            'config_cache_mtime' => 1_000,
            'route_cache_mtime' => 1_000,
            'web_restarted_at' => now()->subMinutes(50),
            'checklist' => ['latest_successful_deploy_confirmed' => true, 'web_php_recycled' => true, 'no_older_php_processes' => true],
            'process_list' => 'test',
            'admin_name' => 'Mother Admin',
        ];
    }
}
