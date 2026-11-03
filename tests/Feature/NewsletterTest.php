<?php

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    NewsletterSubscriber::query()->delete();
});

test('user can subscribe with a new email', function (): void {
    $response = $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
});

test('subscribing with an existing active email returns already_subscribed status', function (): void {
    NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $response = $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $response->assertRedirect();
    $response->assertSessionHas('newsletter_status', 'already_subscribed');
    $this->assertDatabaseCount('newsletter_subscribers', 1);
});

test('resubscribing an inactive email reactivates it', function (): void {
    NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
    // Reactivation keeps the same row (the email column is unique at the
    // schema level), rather than creating a second one — the token is
    // rotated (see FSA-14 tests below), not reused.
    $this->assertDatabaseCount('newsletter_subscribers', 1);
});

// ── FSA-14: token rotation on reactivation ───────────────────────────────────

test('reactivating an inactive subscriber issues a different token', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => $originalToken = Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    expect($subscriber->refresh()->is_active)->toBeTrue();
    expect($subscriber->token)->not->toBe($originalToken);
    expect($subscriber->token)->toHaveLength(64);
});

test('the token issued before reactivation can no longer unsubscribe the subscriber', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => $originalToken = Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => $originalToken]);

    expect($subscriber->refresh()->is_active)->toBeTrue();

    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $originalToken]))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));
});

test('the fresh token issued by reactivation works for the unsubscribe confirmation flow', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);
    $newToken = $subscriber->refresh()->token;

    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $newToken]))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'confirm'));

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => $newToken]);

    expect($subscriber->refresh()->is_active)->toBeFalse();
});

test('an already-active subscriber subscribing again keeps the same token', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => $originalToken = Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    expect($subscriber->refresh()->token)->toBe($originalToken);
});

test('subscribe validates email format', function (): void {
    $this->post('/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['email']);
});

// ── FSA-14: token-protected unsubscribe ──────────────────────────────────────

test('a correct email and token shows the unsubscribe confirmation page', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $subscriber->token]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('newsletter/Unsubscribe')
            ->where('status', 'confirm')
            ->where('email', $subscriber->email));

    // Visiting the confirmation page itself never changes anything.
    expect($subscriber->refresh()->is_active)->toBeTrue();
});

test('confirming with the correct email and token unsubscribes the subscriber', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => $subscriber->token])
        ->assertRedirect(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $subscriber->token]));

    expect($subscriber->refresh()->is_active)->toBeFalse();

    // Revisiting the same link afterwards shows "already unsubscribed", not the confirm form again.
    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $subscriber->token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'already_unsubscribed')
            ->where('email', $subscriber->email));
});

test('a wrong token on unsubscribe leaves the subscriber active', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => 'wrong-token']);

    expect($subscriber->refresh()->is_active)->toBeTrue();
});

test('a missing token on unsubscribe fails validation and leaves the subscriber active', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email])
        ->assertSessionHasErrors('token');

    expect($subscriber->refresh()->is_active)->toBeTrue();
});

test('one subscriber\'s token cannot be used to unsubscribe a different email', function (): void {
    $victim = NewsletterSubscriber::create([
        'email' => 'victim@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);
    $attacker = NewsletterSubscriber::create([
        'email' => 'attacker@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => $victim->email, 'token' => $attacker->token]);

    expect($victim->refresh()->is_active)->toBeTrue();
    expect($attacker->refresh()->is_active)->toBeTrue();
});

test('an invalid token on the confirmation GET changes no subscription state', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => 'wrong-token']))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));

    expect($subscriber->refresh()->is_active)->toBeTrue();
});

test('a nonexistent email on the confirmation GET shows the same generic invalid state', function (): void {
    $this->get(route('newsletter.unsubscribe.confirm', ['email' => 'nobody@example.com', 'token' => 'anything']))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));
});

test('the public subscription-status endpoint no longer exists', function (): void {
    NewsletterSubscriber::create([
        'email' => 'active@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->getJson('/newsletter/status?email=active@example.com')->assertNotFound();
    expect(app('router')->getRoutes()->getByName('newsletter.status'))->toBeNull();
});
