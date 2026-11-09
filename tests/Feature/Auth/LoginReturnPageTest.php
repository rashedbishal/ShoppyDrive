<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function logInWithPassword($testCase, User $user)
{
    return $testCase->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
}

function openLoginPageFrom($testCase, ?string $referer): void
{
    $testCase->get(route('login'), $referer === null ? [] : ['referer' => $referer])->assertOk();
}

test('logging in returns the customer to the page they came from, query string included', function () {
    openLoginPageFrom($this, url('/product/blue-shirt?color=red'));

    logInWithPassword($this, User::factory()->create())->assertRedirect('/product/blue-shirt?color=red');
});

test('the most recent page wins when the customer abandons an earlier login', function () {
    openLoginPageFrom($this, url('/product/first'));
    openLoginPageFrom($this, url('/product/second'));

    logInWithPassword($this, User::factory()->create())->assertRedirect('/product/second');
});

test('a failed login attempt still returns to the original page afterwards', function () {
    $user = User::factory()->create();
    openLoginPageFrom($this, url('/product/blue-shirt'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    openLoginPageFrom($this, url('/login'));

    logInWithPassword($this, $user)->assertRedirect('/product/blue-shirt');
});

test('moving between the auth pages keeps the original page', function (string $authPage) {
    openLoginPageFrom($this, url('/product/blue-shirt'));
    openLoginPageFrom($this, url($authPage));

    logInWithPassword($this, User::factory()->create())->assertRedirect('/product/blue-shirt');
})->with(['/register', '/forgot-password', '/reset-password/some-token', '/email/verify']);

test('opening the login page directly goes home, and forgets any earlier page', function () {
    openLoginPageFrom($this, url('/product/blue-shirt'));
    openLoginPageFrom($this, null);

    logInWithPassword($this, User::factory()->create())->assertRedirect(config('fortify.home'));
});

test('arriving from another website goes home, never back to that website', function (string $referer) {
    openLoginPageFrom($this, $referer);

    logInWithPassword($this, User::factory()->create())->assertRedirect(config('fortify.home'));
})->with([
    'another site' => ['https://evil.example/phish'],
    'look-alike host' => ['https://localhost.evil.example/phish'],
    'not a url' => ['not a url at all'],
]);

test('a same-site address crafted to leave the site goes home', function (string $path) {
    openLoginPageFrom($this, url('/').$path);

    logInWithPassword($this, User::factory()->create())->assertRedirect(config('fortify.home'));
})->with(['//evil.example/phish', '/\\evil.example/phish']);

test('a customer is never sent back to the admin panel', function () {
    openLoginPageFrom($this, url('/admin/orders'));

    logInWithPassword($this, User::factory()->create())->assertRedirect(config('fortify.home'));
});

test('a protected page the customer was stopped at still takes priority', function () {
    $this->get(route('account.orders'))->assertRedirect(route('login'));
    openLoginPageFrom($this, url('/product/blue-shirt'));

    logInWithPassword($this, User::factory()->create())->assertRedirect(route('account.orders'));
});

test('an unverified customer verifies first, then returns to the page', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code' => User::hashEmailVerificationCode('123456', $user->email),
        'email_verification_code_expires_at' => now()->addMinutes(15),
    ])->save();
    openLoginPageFrom($this, url('/product/blue-shirt'));

    logInWithPassword($this, $user)->assertRedirect(route('verification.notice'));

    $this->post(route('verification.verify-code'), ['code' => '123456'])->assertRedirect('/product/blue-shirt');
});

test('google login also returns to the page the customer came from', function () {
    $profile = ['id' => 'google-return', 'name' => 'Jane', 'email' => 'jane-return@example.com'];
    Socialite::fake('google', (new SocialiteUser)->setRaw($profile)->map($profile));
    openLoginPageFrom($this, url('/product/blue-shirt'));

    $this->get('/auth/google/callback')->assertRedirect('/product/blue-shirt');
    $this->assertAuthenticated();
});
