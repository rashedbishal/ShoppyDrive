<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('creates the mother admin account with provided options', function () {
    $this->artisan('admin:create-mother', [
        '--name' => 'Super Admin',
        '--email' => 'super@example.com',
        '--password' => 'password123',
    ])->assertExitCode(0);

    $admin = Admin::where('email', 'super@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_mother)->toBeTrue();
    expect(Hash::check('password123', $admin->password))->toBeTrue();
});

test('replaces an existing mother admin when forced', function () {
    $existing = Admin::create([
        'name' => 'Existing Mother',
        'email' => 'mother@example.com',
        'password' => bcrypt('password'),
        'is_mother' => true,
    ]);

    $this->artisan('admin:create-mother', [
        '--name' => 'New Mother',
        '--email' => 'new-mother@example.com',
        '--password' => 'password123',
        '--force' => true,
    ])->assertExitCode(0);

    expect(Admin::count())->toBe(1);

    $existing->refresh();

    expect($existing->name)->toBe('New Mother');
    expect($existing->email)->toBe('new-mother@example.com');
    expect($existing->is_mother)->toBeTrue();
    expect(Hash::check('password123', $existing->password))->toBeTrue();
});

test('aborts replacing an existing mother admin without confirmation', function () {
    Admin::create([
        'name' => 'Existing Mother',
        'email' => 'mother@example.com',
        'password' => bcrypt('password'),
        'is_mother' => true,
    ]);

    $this->artisan('admin:create-mother', [
        '--name' => 'New Mother',
        '--email' => 'new-mother@example.com',
        '--password' => 'password123',
    ])
        ->expectsConfirmation('A mother admin already exists (mother@example.com). Replace it with the new details?', 'no')
        ->assertExitCode(1);

    expect(Admin::where('email', 'mother@example.com')->exists())->toBeTrue();
    expect(Admin::count())->toBe(1);
});

test('rejects an invalid email', function () {
    $this->artisan('admin:create-mother', [
        '--name' => 'Super Admin',
        '--email' => 'not-an-email',
        '--password' => 'password123',
    ])->assertExitCode(1);

    expect(Admin::count())->toBe(0);
});
