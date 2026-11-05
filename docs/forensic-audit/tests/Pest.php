<?php

use App\Models\Admin;
use App\Support\AdminSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

// Shared helpers (createAdmin and others) used by the race copies in ./race.
require_once __DIR__.'/../../../tests/Pest.php';

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

/**
 * Convention for the audit tests: each test states the CORRECT behaviour.
 * A FAIL therefore confirms the finding named in the test title.
 * A PASS means the suspected defect did not reproduce.
 *
 * @param  array<string, list<string>>|null  $permissions
 */
function auditAdmin(?array $permissions = null, bool $isMother = false): Admin
{
    $admin = new Admin;
    $admin->forceFill([
        'name' => 'Audit Admin',
        'email' => 'audit-admin-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'permissions' => $permissions ?? collect(AdminSections::SECTIONS)->keys()
            ->mapWithKeys(fn (string $section): array => [$section => AdminSections::abilitiesFor($section)])->all(),
        'is_mother' => $isMother,
    ])->save();

    return $admin;
}

/** @return array<string, mixed> */
function auditCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Audit Buyer',
        'email' => 'audit-buyer@example.test',
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}
