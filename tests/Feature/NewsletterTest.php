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
    // The page shows the "Thank you for subscribing" modal from this.
    $response->assertInertiaFlash('newsletter.status', 'subscribed');
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
    $response->assertInertiaFlash('newsletter.status', 'already_subscribed');
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

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com'])
        ->assertInertiaFlash('newsletter.status', 'resubscribed');

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

    $link = route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $subscriber->token]);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => $subscriber->token])
        ->assertRedirect($link);

    expect($subscriber->refresh()->is_active)->toBeFalse();

    // Straight after confirming, the same link shows the success page once.
    $this->get($link)->assertInertia(fn (Assert $page) => $page
        ->component('newsletter/Unsubscribe')
        ->where('status', 'unsubscribed')
        ->where('email', $subscriber->email));

    // Reusing the link later (or refreshing that page) leads to the home page.
    $this->get($link)
        ->assertRedirect(route('home'))
        ->assertInertiaFlash('toast.message', "You're already unsubscribed from our newsletter.");
});

test('an unsubscribed email link opened later, without confirming first, leads to the home page', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'gone@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subMonth(),
        'unsubscribed_at' => now()->subWeek(),
    ]);

    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $subscriber->token]))
        ->assertRedirect(route('home'));
});

test('a repeated confirm for an already unsubscribed address does not show the success page again', function (): void {
    $subscriber = NewsletterSubscriber::create([
        'email' => 'twice@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subMonth(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => $subscriber->email, 'token' => $subscriber->token])
        ->assertSessionMissing('newsletter_unsubscribed');
});

test('a success flag for one address cannot show another address as unsubscribed', function (): void {
    $other = NewsletterSubscriber::create([
        'email' => 'other@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subMonth(),
        'unsubscribed_at' => now(),
    ]);

    $this->withSession(['newsletter_unsubscribed' => 'someone-else@example.com'])
        ->get(route('newsletter.unsubscribe.confirm', ['email' => $other->email, 'token' => $other->token]))
        ->assertRedirect(route('home'));
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

test('an email with a local part over 64 characters is rejected', function (): void {
    $response = $this->post('/newsletter/subscribe', [
        'email' => str_repeat('a', 65).'@example.com',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('newsletter_subscribers', 0);
});

test('an email longer than 254 characters is rejected', function (): void {
    $domain = implode('.', array_fill(0, 4, str_repeat('b', 60))).'.com';
    $email = str_repeat('a', 64).'@'.$domain;
    expect(strlen($email))->toBeGreaterThan(254);

    $response = $this->post('/newsletter/subscribe', ['email' => $email]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('newsletter_subscribers', 0);
});

test('an email with a 64 character local part is still accepted', function (): void {
    $email = str_repeat('a', 64).'@example.com';

    $this->post('/newsletter/subscribe', ['email' => $email])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('newsletter_subscribers', ['email' => $email]);
});

test('an invalid email gets a validation error and no thank-you', function (): void {
    $this->post('/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email')
        ->assertInertiaFlashMissing('newsletter');

    $this->assertDatabaseCount('newsletter_subscribers', 0);
});

test('unsubscribing still works after the sign-up limit is used up on the same network', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('newsletter.subscribe'), ['email' => "signup{$attempt}@example.com"]);
    }
    $this->post(route('newsletter.subscribe'), ['email' => 'one-more@example.com'])
        ->assertSessionHasErrors('rate_limit');

    $subscriber = NewsletterSubscriber::create([
        'email' => 'leaving@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post(route('newsletter.unsubscribe'), ['email' => $subscriber->email, 'token' => $subscriber->token])
        ->assertSessionHasNoErrors();

    expect($subscriber->refresh()->is_active)->toBeFalse();
});
