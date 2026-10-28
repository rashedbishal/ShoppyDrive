<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('deletes expired password reset tokens without recursing into itself', function () {
    DB::table('password_reset_tokens')->insert([
        'email' => 'expired@example.com',
        'token' => 'expired-token',
        'created_at' => now()->subDays(2),
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => 'fresh@example.com',
        'token' => 'fresh-token',
        'created_at' => now(),
    ]);

    // A command that shared Laravel's own `auth:clear-resets` name would
    // recurse into itself indefinitely here — this call completing at all
    // (let alone within the test runner's normal time budget) proves the
    // rename resolved that collision.
    $this->artisan('app:clear-expired-resets')
        ->expectsOutput('Expired password reset tokens deleted.')
        ->assertSuccessful();

    expect(DB::table('password_reset_tokens')->where('email', 'expired@example.com')->exists())->toBeFalse();
    expect(DB::table('password_reset_tokens')->where('email', 'fresh@example.com')->exists())->toBeTrue();
});
