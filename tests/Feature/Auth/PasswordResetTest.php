<?php

use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Carbon\Carbon;
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

test('the forgot-password screen starts with no resend cooldown', function () {
    $this->get(route('password.request'))
        ->assertInertia(fn ($page) => $page->where('resendAvailableAt', null));
});

test('a tester could spam the button; requesting a link now sets a cooldown deadline on the next screen load', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->travelTo(now());
    $this->post(route('password.email'), ['email' => $user->email]);

    $response = $this->get(route('password.request'));

    $response->assertInertia(function ($page) {
        $deadline = $page->toArray()['props']['resendAvailableAt'];
        expect($deadline)->not->toBeNull();
        expect(Carbon::parse($deadline)->diffInSeconds(now(), true))->toBeGreaterThan(85)->toBeLessThan(95);
    });
});

test('the cooldown is set even for an email that does not exist, so timing cannot reveal whether an account exists', function () {
    $this->post(route('password.email'), ['email' => 'nobody@example.com']);

    $this->get(route('password.request'))
        ->assertInertia(fn ($page) => $page->where('resendAvailableAt', fn ($value) => $value !== null));
});

test('the cooldown deadline is in the past once it has fully elapsed, so the client re-enables the button', function () {
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);
    $this->travel(91)->seconds();

    $response = $this->get(route('password.request'));

    $response->assertInertia(function ($page) {
        $deadline = $page->toArray()['props']['resendAvailableAt'];
        expect(Carbon::parse($deadline)->isPast())->toBeTrue();
    });
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('an anonymized account never receives a reset link, since it can never complete one anyway', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->anonymize();

    $response = $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertNothingSent();
    // SEC-015: the response must stay generic either way, not leak
    // whether a notification was actually sent.
    $response->assertSessionHas('status', GenericPasswordResetLinkResponse::MESSAGE);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) use ($user) {
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

    Notification::assertSentToTimes($user, QueuedResetPassword::class, 1);
});

test('forgot password answers fetch callers the same way (SEC-015)', function () {
    $this->postJson(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJson(['message' => GenericPasswordResetLinkResponse::MESSAGE]);
});
