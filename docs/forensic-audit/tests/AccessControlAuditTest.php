<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('S01 a verification code sent to one email must not verify a different email', function () {
    $user = User::factory()->create(['email' => 'attacker@example.test', 'email_verified_at' => null]);
    $user->forceFill([
        'email_verification_code' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->addMinutes(15),
        'email_verification_code_sent_at' => now()->subMinutes(2),
    ])->save();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => 'victim@example.test']);
    expect($user->fresh()->email)->toBe('victim@example.test');

    $this->actingAs($user->fresh())->post(route('verification.verify-code'), ['code' => '123456']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('S02 customer OAuth tokens must not reach the admin customer page', function () {
    $user = User::factory()->create();
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => '1', 'token' => 'audit-canary-token', 'refresh_token' => 'audit-canary-refresh']);

    $response = $this->actingAs(auditAdmin(), 'admin')->get(route('admin.customers.show', $user));
    $response->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('audit-canary');
});

test('S03 customer OAuth tokens must not reach the customer own browser', function () {
    $user = User::factory()->create();
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => '2', 'token' => 'audit-canary-token', 'refresh_token' => 'audit-canary-refresh']);

    $response = $this->actingAs($user)->get(route('connections.edit'));
    $response->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('audit-canary');
});

test('S04 a view-only SMS forwarder admin must not receive the full token', function () {
    config(['services.sms_forwarder.token' => 'audit-canary-forwarder-token']);
    $envFile = base_path('.env');
    $response = $this->actingAs(auditAdmin(['sms_forwarder' => ['view']]), 'admin')->get(route('admin.settings.sms-forwarder.index'));
    $response->assertOk();

    expect(array_key_exists('fullToken', $response->viewData('page')['props']))->toBeFalse();
});

test('S05 password reset links must not use a Host header chosen by the requester', function () {
    Notification::fake();
    config(['app.url' => 'https://shoppydrive.test']);
    $user = User::factory()->create(['email' => 'reset@example.test']);

    $this->withServerVariables(['HTTP_HOST' => 'evil.example'])->post('http://evil.example/forgot-password', ['email' => 'reset@example.test']);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;
        expect($url)->not->toContain('evil.example');

        return true;
    });
});

test('S06 logging out of admin when not logged in must not crash', function () {
    expect($this->post('/admin/logout')->status())->toBeLessThan(500);
});

test('S07 an array in the login email field must not crash', function () {
    expect($this->post('/login', ['email' => ['x'], 'password' => 'x'])->status())->toBeLessThan(500);
});
