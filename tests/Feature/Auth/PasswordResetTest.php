<?php

use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('reset password page only calls registered route names', function () {
    $source = file_get_contents(resource_path('js/pages/auth/reset-password.tsx'));

    preg_match_all("/\broute\(\s*'([^']+)'/", $source, $matches);

    expect($matches[1])->toContain('password.update');

    foreach ($matches[1] as $routeName) {
        expect(Route::has($routeName))->toBeTrue("Route [{$routeName}] is not registered.");
    }
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('forgot password gives the same answer whether or not the email has an account (SEC-015)', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);
    $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@example.com']);
    // Inside the broker's 60-second throttle, which only a real account can hit.
    $repeat = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);

    foreach ([$known, $unknown, $repeat] as $response) {
        $response->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE);
    }

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('forgot password answers fetch callers the same way (SEC-015)', function () {
    $this->postJson(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJson(['message' => GenericPasswordResetLinkResponse::MESSAGE]);
});
